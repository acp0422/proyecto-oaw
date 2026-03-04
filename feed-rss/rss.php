<?php
header("Content-Type: application/json; charset=utf-8");

function postNew($feedUrl){
    $newsList = fetchFeedData($feedUrl);
    saveNewsToCsv($newsList);
    echo json_encode(["status" => "success", "message" => "News updated successfully"]);
}

function fetchFeedData($feedUrl){
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
        echo json_encode(["status"=>"error","message"=>"Error descargando el feed (URL inválida o bloqueada)"]);
        exit;
    }

    $xml = @simplexml_load_string($xmlText);
    if ($xml === false) {
        http_response_code(400);
        echo json_encode(["status"=>"error","message"=>"Error parseando XML (no parece RSS válido)"]);
        exit;
    }

    if (!isset($xml->channel->item)) {
        http_response_code(400);
        echo json_encode(["status"=>"error","message"=>"El feed no tiene channel->item (podría ser Atom)"]);
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

function saveNewsToCsv($newsList, $csvFile = null){

    $csvPath = $csvFile ?? (__DIR__ . "/news.csv");

    $fileExists = file_exists($csvPath);

    $file = fopen($csvPath, "a");
    if(!$file){
        http_response_code(500);
        echo json_encode(["status"=>"error","message"=>"No se pudo abrir el CSV: $csvPath"]);
        exit;
    }

    if(!$fileExists){
        fputcsv($file, ["fecha","titulo","url","descripcion","categorias"]);
    }

    foreach($newsList as $news){
        fputcsv($file, [
            $news["date"],
            $news["title"],
            $news["url"],
            $news["description"],
            $news["categories"]
        ]);
    }

    fclose($file);
}

function readNewsFromCsv(): array {

    $csvFile = $csvFile ?? (__DIR__ . "/news.csv");
    if (!file_exists($csvFile)) {
        return [];
    }

    $fp = fopen($csvFile, "r");
    if (!$fp) {
        http_response_code(500);
        echo json_encode(["status"=>"error","message"=>"No se pudo leer el CSV"]);
        exit;
    }

    fgetcsv($fp);

    $rows = [];
    while (($data = fgetcsv($fp)) !== false) {
        $rows[] = [
            "date" => $data[0] ?? "",
            "title" => $data[1] ?? "",
            "url" => $data[2] ?? "",
            "description" => $data[3] ?? "",
            "categories" => isset($data[4]) && $data[4] !== "" ? explode("|", $data[4]) : []
        ];
    }

    fclose($fp);
    return $rows;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST["feedUrl"]) || trim($_POST["feedUrl"]) === "") {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "feedUrl is required"]);
        exit;
    }

    postNew(trim($_POST["feedUrl"]));
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $news = readNewsFromCsv();
    echo json_encode(["status" => "success", "news" => $news]);
    exit;
}

http_response_code(405);
echo json_encode(["status" => "error", "message" => "Method not allowed"]);