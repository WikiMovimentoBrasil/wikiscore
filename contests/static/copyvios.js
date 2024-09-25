// Constants for URLs and error messages
const COPYVIOS_BASE_URL = 'https://copyvios.toolforge.org/';
const COPYVIOS_API = COPYVIOS_BASE_URL + 'api.json';

// Function to fetch language data from the API
async function fetchSitesData() {
    const apiUrl = `${COPYVIOS_API}?action=sites`;

    try {
        const response = await fetch(apiUrl);
        if (!response.ok) {
            throw new Error('Network error');
        }
        const data = await response.json();
        if (data.status === 'ok' && Array.isArray(data.langs) && Array.isArray(data.projects)) {
            // Extract language codes from the "langs" array
            const languageArray = data.langs.map(langData => langData[0]);
            // Extract project codes from the "projects" array
            const projectsArray = data.projects.map(projectData => projectData[0]);
            return { languageArray, projectsArray };
        } else {
            throw new Error('Invalid API response');
        }
    } catch (error) {
        throw new Error('There was a problem with the fetch operation:', error);
    }
}

function hideCopyvioLink() {
    const copyvioLink = document.getElementById("a_copyvios");
    const spanCopyvio = document.getElementById("span_copyvios");
    if (copyvioLink) {
        copyvioLink.style.display = 'none';
    }
    if (spanCopyvio) {
        spanCopyvio.innerHTML = '<i class="fa-solid fa-spinner w3-spin"></i>';
    }
}

function displayCopyvioResult(result) {
    const spanCopyvio = document.getElementById("span_copyvios");
    if (spanCopyvio) {
        spanCopyvio.innerHTML = result;
    }
}

function handleCopyviosError(error) {
    const spanCopyvio = document.getElementById("span_copyvios");
    if (spanCopyvio) {
        spanCopyvio.innerHTML = '<i class="fa-solid fa-question w3-text-red"></i>';
    }
    console.error('Error during fetch operation:', error);
    throw new Error('There was a problem with the fetch operation.');
}

async function calculateCopyvios(revid, urlString) {
    // Hide the link to avoid duplicity
    hideCopyvioLink();

    // Parse the URL
    const url = new URL(urlString);
    const hostnameParts = url.hostname.split('.');

    if (hostnameParts.length !== 3) {
        displayCopyvioResult('Invalid URL');
        return;
    }

    const project = hostnameParts[hostnameParts.length - 2];
    const lang = hostnameParts[hostnameParts.length - 3];

    try {
        const sitesData = await fetchSitesData();

        if (!sitesData.projectsArray.includes(project) || !sitesData.languageArray.includes(lang)) {
            displayCopyvioResult('Unsupported language or wiki');
            return;
        }

        const jsonUrl = `${COPYVIOS_API}?version=1&lang=${lang}&project=${project}&action=search&use_engine=1&use_links=1&turnitin=0&oldid=${revid}`;
        const response = await fetch(jsonUrl);

        if (!response.ok) {
            throw new Error('Network error');
        }

        const data = await response.json();

        // Calculate the percentage
        const percentage = data.best.confidence * 100;

        // Update the DOM with the calculated percentage
        const copyvioLink = `${COPYVIOS_BASE_URL}?lang=${lang}&project=${project}&action=search&use_engine=1&use_links=1&turnitin=0&oldid=${revid}`;
        const percentageText = percentage.toFixed(1) + '%';
        const linkText = `<a target="_blank" rel="noopener" href="${copyvioLink}">${percentageText}<i class="fa-solid fa-arrow-up-right-from-square fa-xs"></i></a>`;
        displayCopyvioResult(linkText);
    } catch (error) {
        handleCopyviosError(error);
    }
}
