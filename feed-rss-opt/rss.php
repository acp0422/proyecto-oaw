<?php
header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . '/conexion.php';

function postNew($feedUrl)
{
  $newsList = fetchFeedData($feedUrl);
  saveNewsToDb($newsList);
  echo json_encode(["status" => "success", "message" => "Noticias actualizadas y guardadas en MySQL con éxito"]);
}

function fetchFeedData($feedUrl)
{
  $context = stream_context_create([
    "http" => [
      "method" => "GET",
      "header" => "User-Agent: rss-reader/1.0\r\n",
      "timeout" => 15
    ]
  ]);

  $xmlText = @file_get_contents($feedUrl, false, $context);
  if ($xmlText === false) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Error descargando el feed (URL inválida o bloqueada)"]);
    exit;
  }

  $xml = @simplexml_load_string($xmlText);
  if ($xml === false) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Error parseando XML (no parece RSS válido)"]);
    exit;
  }

  if (!isset($xml->channel->item)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "El feed no tiene channel->item (podría ser Atom)"]);
    exit;
  }

  $newsList = [];

  foreach ($xml->channel->item as $item) {
    $categories = [];
    if (isset($item->category)) {
      foreach ($item->category as $cat) {
        $categories[] = (string)$cat;
      }
    }

    $newsList[] = [
      "date" => (string)($item->pubDate ?? ""),
      "title" => (string)($item->title ?? ""),
      "url" => (string)($item->link ?? ""),
      "description" => (string)($item->description ?? ""),
      "categories" => implode("|", $categories)
    ];
  }

  return $newsList;
}

// Guarda las noticias en MySQL ignorando duplicados
function saveNewsToDb($newsList)
{
  $pdo = getConexion();

  // El uso de INSERT IGNORE hace que si la URL ya existe,
  // MySQL simplemente ignore esa fila en lugar de lanzar un error o duplicarla.
  $sql = "INSERT IGNORE INTO noticias (fecha_pub, titulo, url, descripcion, categorias) 
          VALUES (:fecha_pub, :titulo, :url, :descripcion, :categorias)";
          
  $stmt = $pdo->prepare($sql);

  foreach ($newsList as $news) {
    $stmt->execute([
      ':fecha_pub'   => $news["date"],
      ':titulo'      => $news["title"],
      ':url'         => $news["url"],
      ':descripcion' => $news["description"],
      ':categorias'  => $news["categories"]
    ]);
  }
}

// Obtiene, filtra y ordena las noticias directamente usando SQL
function readNewsFromDb($buscar = '', $categoria = '', $ordenar = 'date', $direccion = 'DESC'): array
{
  $pdo = getConexion();

  // Mapeamos los campos que vienen del frontend con las columnas reales de la tabla
  $camposPermitidos = [
    'date'        => 'creado_el',
    'title'       => 'titulo',
    'url'         => 'url',
    'description' => 'descripcion',
    'categories'  => 'categorias'
  ];

  $columnadeOrden = $camposPermitidos[$ordenar] ?? 'creado_el';
  $direccion = strtoupper($direccion) === 'ASC' ? 'ASC' : 'DESC';

  // Base de la consulta
  $sql = "SELECT id, fecha_pub AS date, titulo AS title, url, descripcion AS description, categorias 
          FROM noticias WHERE 1=1";
  
  $params = [];

  // Filtro de búsqueda (Título o Descripción)
  if (!empty($buscar)) {
    $sql .= " AND (titulo LIKE :buscar OR descripcion LIKE :buscar)";
    $params[':buscar'] = '%' . $buscar . '%';
  }

  // Filtro por categoría
  if (!empty($categoria)) {
    $sql .= " AND categorias LIKE :categoria";
    $params[':categoria'] = '%' . $categoria . '%';
  }

  // Ordenamiento nativo de la base de datos
  $sql .= " ORDER BY $columnadeOrden $direccion";

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll();

  // Procesamiento del string de categorías para volverlo a convertir en un array
  foreach ($rows as &$row) {
    $row['categories'] = !empty($row['categorias']) ? explode("|", $row['categorias']) : [];
    unset($row['categorias']); // Limpiamos la columna temporal de texto plano
  }

  return $rows;
}

// Manejo de peticiones POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  if (!isset($_POST["feedUrl"]) || trim($_POST["feedUrl"]) === "") {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "feedUrl is required"]);
    exit;
  }

  postNew(trim($_POST["feedUrl"]));
  exit;
}

// Manejo de peticiones GET (Filtrado y listado desde MySQL)
if ($_SERVER["REQUEST_METHOD"] === "GET") {

  $buscar    = isset($_GET["buscar"]) ? trim($_GET["buscar"]) : '';
  $categoria = isset($_GET["categoria"]) ? trim($_GET["categoria"]) : '';
  $ordenar   = isset($_GET["ordenar"]) ? trim($_GET["ordenar"]) : 'date';
  $direccion = isset($_GET["dir"]) ? trim($_GET["dir"]) : 'DESC';

  // Búsqueda, filtrado y ordenación directamente a MySQL
  $news = readNewsFromDb($buscar, $categoria, $ordenar, $direccion);

  echo json_encode([
    "status" => "success",
    "news" => $news,
    "total" => count($news),
    "filtros" => [
      "buscar" => $buscar ?: null,
      "categoria" => $categoria ?: null
    ],
    "ordenamiento" => [
      "campo" => $ordenar,
      "direccion" => $direccion
    ]
  ]);
  exit;
}

http_response_code(405);
echo json_encode(["status" => "error", "message" => "Method not allowed"]);