// Variables globales
let currentFilters = {
  buscar: '',
  categoria: '',
  ordenar: 'date',
  direccion: 'DESC'
};

// Función newsUpdate V2
async function newsUpdate(filters = {}) {
  try {
    console.log("Loading news (GET) ...");

    let url = "/tareas_web/feedRSS/rss.php";
    const params = new URLSearchParams();
    
    if (filters.buscar) params.append('buscar', filters.buscar);
    if (filters.categoria) params.append('categoria', filters.categoria);
    if (filters.ordenar) params.append('ordenar', filters.ordenar);
    if (filters.direccion) params.append('dir', filters.direccion);
    
    if (params.toString()) {
      url += "?" + params.toString();
    }

    const res = await fetch(url, { method: "GET" });
    const data = await res.json();

    if (!res.ok || data.status !== "success") {
      throw new Error(data.message || "Error loading news");
    }

    const normalizedNews = (data.news || []).map(n => ({
      title: n.title ?? "",
      description: n.description ?? "",
      url: n.url ?? "",
      date: n.date ?? "",
      category: Array.isArray(n.categories) ? n.categories.join(" | ")
              : (n.categories ?? n.category ?? "")
    }));

    showNews(normalizedNews);

  } catch (err) {
    console.error(err);
    alert("No se pudieron cargar las noticias.");
  }
}

async function postUpdateNews(event){

  event.preventDefault();

  const rssUrl = document.querySelector("input[name='rssUrl']").value;

  const res = await fetch("/tareas_web/feedRSS/rss.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded"
    },
    body: "feedUrl=" + encodeURIComponent(rssUrl)
  });

  const data = await res.json();

  console.log(data);

  alert(data.message);

}

function showNews(newsList) {

  const container = document.getElementById("news-container");

  container.innerHTML = "<h1>News</h1>";

  newsList.forEach(news => {

    const div = document.createElement("div");

    div.classList.add("news-item");

    div.innerHTML = `
      <h3>${news.title}</h3>
      <p><strong>Description:</strong> ${news.description}</p>
      <p class="news-date"><strong>Date:</strong> ${news.date}</p>
      <p class="news-category"><strong>Category:</strong> ${news.category}</p>
      <a href="${news.url}" target="_blank">read more</a>
    `;

    container.appendChild(div);

  });

}

// Función para aplicar filtros desde el HTML
function applyFilters() {
  currentFilters = {
    buscar: document.getElementById("searchInput")?.value || '',
    categoria: document.getElementById("categoryFilter")?.value || '',
    ordenar: document.getElementById("sortBy")?.value || 'date',
    direccion: document.getElementById("sortDir")?.value || 'DESC'
  };
  
  newsUpdate(currentFilters);
}

// Función para limpiar filtros
function clearFilters() {
  
  if (document.getElementById("searchInput")) 
    document.getElementById("searchInput").value = '';
  if (document.getElementById("categoryFilter")) 
    document.getElementById("categoryFilter").value = '';
  if (document.getElementById("sortBy")) 
    document.getElementById("sortBy").value = 'date';
  if (document.getElementById("sortDir")) 
    document.getElementById("sortDir").value = 'DESC';
  
  newsUpdate({});
}

// Cargar noticias al inicio
document.addEventListener('DOMContentLoaded', function() {
  newsUpdate({});
});
