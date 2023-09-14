// Constants for URLs and error messages
const WIKIWHO_BASE_URL = 'https://wikiwho-api.wmcloud.org/';
const LANGUAGE_ARRAY = ["ar", "de", "en", "es", "eu", "fr", "hu", "id", "it", "ja", "nl", "pl", "pt", "tr"];
const WIKITYPE_ARRAY = ["wikipedia"];

function hideAuthorshipLink() {
  const authorshipLink = document.getElementById("a_authorship");
  const spanAuthorship = document.getElementById("span_authorship");
  if (authorshipLink) {
    authorshipLink.style.display = 'none';
  }
  if (spanAuthorship) {
    spanAuthorship.innerHTML = '<i class="fa-solid fa-spinner w3-spin"></i>';
  }
}

function updateAuthorshipPercentage(percentage) {
  const spanAuthorship = document.getElementById("span_authorship");
  if (spanAuthorship) {
    spanAuthorship.innerHTML = percentage + '%';
  }
}

function handleFetchError(error) {
  const spanAuthorship = document.getElementById("span_authorship");
  if (spanAuthorship) {
    spanAuthorship.innerHTML = '<i class="fa-solid fa-question w3-text-red"></i>';
  }
  console.error('Error during fetch operation:', error);
  throw new Error('There was a problem with the fetch operation.');
}

function calculateAuthorship(revid, urlString) {
  // Hide the link to avoid duplicity
  hideAuthorshipLink();

  // Parse the URL
  const url = new URL(urlString);
  const hostnameParts = url.hostname.split('.');

  if (hostnameParts.length !== 3) {
    return Promise.reject(new Error('Invalid URL'));
  }

  const wikiType = hostnameParts[hostnameParts.length - 2];
  const lang = hostnameParts[hostnameParts.length - 3];

  if (!WIKITYPE_ARRAY.includes(wikiType) || !LANGUAGE_ARRAY.includes(lang)) {
    return Promise.reject(new Error('Unsupported language or wiki'));
  }

  const jsonUrl = `${WIKIWHO_BASE_URL}${lang}/api/v1.0.0-beta/rev_content/rev_id/${revid}/?o_rev_id=true&editor=true&token_id=false&out=false&in=false`;

  return fetch(jsonUrl)
    .then(response => {
      if (!response.ok) {
        throw new Error('Network error');
      }
      return response.json();
    })
    .then(data => {
      // Initialize variables to store character sums
      let totalSumAllStr = 0;
      let totalSumEditorMatch = 0;

      // Loop through the revisions and find the specified revid
      for (const revision of data.revisions) {
        if (revision.hasOwnProperty(revid)) {
          const revisionData = revision[revid];
          const editorId = revisionData.editor;
          // Loop through tokens in the found revision
          for (const token of revisionData.tokens) {
            // Calculate the sum of characters for all "str" keys
            totalSumAllStr += token.str.length;
            // Check if the "editor" matches the specified editorId
            if (token.editor === editorId) {
              // Calculate the sum of characters for matching editors
              totalSumEditorMatch += token.str.length;
            }
          }
        }
      }

      // Calculate the percentage
      const percentage = (totalSumEditorMatch / totalSumAllStr) * 100;

      // Update the DOM with the calculated percentage
      updateAuthorshipPercentage(percentage.toFixed(2));

      // Return the calculated percentage
      return percentage.toFixed(2);
    })
    .catch(handleFetchError);
}
