function toggleTable(titleElem) {
    const envDiv = titleElem.closest('.env');
    envDiv.classList.toggle('collapsed');
}

function sortTable(th) {
    const table = th.closest("table");
    const tbody = table.querySelector("tbody");
    const rows = Array.from(tbody.querySelectorAll("tr"));
    const index = Array.from(th.parentNode.children).indexOf(th);
    const isNumeric = th.textContent.toLowerCase().includes("pr") || th.textContent.toLowerCase().includes("version");
    const isAsc = th.classList.toggle("sort-asc");

    rows.sort((a, b) => {
        const cellA = a.children[index].textContent.trim();
        const cellB = b.children[index].textContent.trim();

        if (isNumeric) {
            const numA = parseFloat(cellA) || 0;
            const numB = parseFloat(cellB) || 0;
            return isAsc ? numA - numB : numB - numA;
        } else {
            return isAsc ? cellA.localeCompare(cellB) : cellB.localeCompare(cellA);
        }
    });

    rows.forEach(row => tbody.appendChild(row));

    th.parentNode.querySelectorAll("th").forEach(thEl => {
        if (thEl !== th) thEl.classList.remove("sort-asc");
    });
}

function getStatusClass(status) {
    if (!status) return 'unknown';
    if (status >= 200 && status < 300) return 'success';
    if (status >= 300 && status < 400) return 'redirect';
    if (status >= 400 && status < 500) return 'client-error';
    if (status >= 500) return 'server-error';
    return 'unknown';
}

document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll("[data-github-version-service]").forEach(cell => {
        const serviceName = encodeURIComponent(cell.getAttribute("data-github-version-service"));
        const envName = encodeURIComponent(cell.getAttribute("data-github-version-env"));

        if (!serviceName || serviceName === '—' || !envName || envName === '—') {
            cell.textContent = "—";
            return;
        }

        fetch(`/github_latest_version.php?service=${ serviceName }&env=${ envName }`)
            .then(resp => resp.json())
            .then(data => {
                if(!data.tag_name) {
                    cell.textContent = "—";
                } else {
                    const $date = new Date(data.published_at);
                    cell.textContent = `${data.tag_name} (${$date.toLocaleString('nl-NL', { dateStyle: 'full', timeStyle: 'short' })})`;
                }
            })
            .catch(() => {
                cell.textContent = "error";
            });
    });
    document.querySelectorAll("[data-github-service]").forEach(cell => {
        const serviceName = encodeURIComponent(cell.getAttribute("data-github-service"));
        const envName = encodeURIComponent(cell.getAttribute("data-github-env"));

        if (!serviceName || serviceName === '—' || !envName || envName === '—') {
            cell.textContent = "—";
            return;
        }

        fetch(`/github_prs.php?service=${ serviceName }&env=${ envName }`)
            .then(resp => resp.json())
            .then(data => {
                cell.textContent = data.pull_requests ?? "—";
            })
            .catch(() => {
                cell.textContent = "error";
            });
    });

    document.querySelectorAll("[data-version-service]").forEach(span => {
        const serviceName = encodeURIComponent(span.getAttribute("data-version-service"));
        const envName = encodeURIComponent(span.getAttribute("data-version-env"));

        if (!serviceName || serviceName === '—' || !envName || envName === '—') {
            cell.textContent = "—";
            return;
        }

        fetch(`/check_version.php?service=${ serviceName }&env=${ envName }`)
            .then(resp => resp.json())
            .then(data => {
                const version = data.version || "unknown";
                const shortRef = data.git_ref ? data.git_ref.substring(0, 8) : "";
                span.textContent = shortRef ? `${version} (${shortRef})` : version;
            })
            .catch(() => {
                span.textContent = "error";
            });
    });

    // Fetch HTTP status codes
    document.querySelectorAll(".status[data-status-service]").forEach(cell => {
        const serviceName = encodeURIComponent(cell.getAttribute("data-status-service"));
        const envName = encodeURIComponent(cell.getAttribute("data-status-env"));

        if (!serviceName || serviceName === '—' || !envName || envName === '—') {
            cell.textContent = "—";
            return;
        }

        fetch(`/check_status.php?service=${serviceName}&env=${ envName }`, {
            method: 'GET',
            cache: 'no-cache',
            headers: {
                'Accept': 'application/json'
            }
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    let errorText = 'error';
                    if (data.error === 'connection_failed') errorText = 'connection failed';
                    if (data.error === 'host_not_found') errorText = 'host not found';

                    cell.textContent = errorText;
                    cell.classList.add('unknown');
                } else {
                    cell.textContent = data.http_status;
                    cell.classList.add(getStatusClass(data.http_status));
                }
            })
            .catch(error => {
                console.error('Error checking status:', error);
                cell.textContent = "error";
                cell.classList.add('unknown');
            });
    });
});