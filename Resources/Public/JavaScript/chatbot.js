document.addEventListener('DOMContentLoaded', function () {
    const fab = document.getElementById('w3c-ai-chatbot-fab');
    const chatWindow = document.getElementById('w3c-ai-chat-wrapper');
    const chatHeader = document.querySelector('.w3c-ai-chat-header');

    if (fab) {
        fab.addEventListener('click', () => {
            chatWindow.classList.toggle('hidden');
            chatMessages.scrollTop = chatMessages.scrollHeight;
        });
    }

    if (chatHeader) {
        chatHeader.addEventListener('click', () => {
            chatWindow.classList.add('hidden');
        });
    }

    const chatbotContainer = document.getElementById('chatbot-container');
    if (!chatbotContainer) {
        return;
    }
    const pageId = chatbotContainer.dataset.pageId;
    const sendButton = document.getElementById('chatbot-send');
    const chatInput = document.getElementById('chatbot-input');
    const chatMessages = document.getElementById('chatbot-messages');
    const solrResultsContainer = document.getElementById('solr-results');

    let stream = window.aichatbotStream;

    if (!stream) {
        sendButton.addEventListener('click', sendMessage);
        chatInput.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });
    }else{
        sendButton.addEventListener('click', sendMessageStream);
        chatInput.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                sendMessageStream();
            }
        });
    }

    function truncateGracefully(text) {
        if (!text) {
            return '';
        }
        // A simple heuristic: if the text does not end with a newline, it's likely been truncated.
        if (text.slice(-1) !== "\n") {
            const lastNewlinePos = text.lastIndexOf("\n");
            if (lastNewlinePos !== -1) {
                // Return text up to the last newline, effectively removing the incomplete last line.
                return text.substring(0, lastNewlinePos);
            }
        }
        return text;
    }

    function sendMessage() {
        const question = chatInput.value;
        if (question.trim() === '') {
            return;
        }

        appendMessage('user', question);
        chatInput.value = '';

        // Add loading indicator for bot message
        const loadingElement = appendMessage('bot', '...');

        const formData = new FormData();
        formData.append('tx_w3caichatbot_chatbotajax[question]', question);
        formData.append('tx_w3caichatbot_chatbotajax[action]', 'ask');
        formData.append('tx_w3caichatbot_chatbotajax[controller]', 'Chatbot');

        fetch(`/index.php?id=${pageId}&type=2999`, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            // Update bot message with actual answer
            loadingElement.innerHTML = data.answer;
            if (solrResultsContainer) {
                displaySolrResults(data.solrResults);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            loadingElement.innerText = 'Sorry, something went wrong.';
        });
    }

    async function sendMessageStream() {
        const question = chatInput.value;
        if (question.trim() === '') {
            return;
        }

        appendMessage('user', question);
        chatInput.value = '';

        // Créez le conteneur pour la réponse du bot
        const botMessageElement = appendMessage('bot', '...'); // Ajout d'un indicateur de chargement
        let fullMarkdownContent = ''; // Variable pour accumuler le texte

        // Construisez l'URL pour la requête GET
        const url = new URL(`/index.php?id=${pageId}&type=2999`, window.location.origin);
        url.searchParams.append('tx_w3caichatbot_chatbotajax[question]', question);
        url.searchParams.append('tx_w3caichatbot_chatbotajax[action]', 'askStream');
        url.searchParams.append('tx_w3caichatbot_chatbotajax[controller]', 'Chatbot');

        try {
            const eventSource = new EventSource(url.toString());

            // 1. S'exécute chaque fois qu'un message (un "chunk") est reçu
            eventSource.onmessage = (event) => {
                // EventSource a déjà extrait le contenu de "data: ". On a juste à le parser.
                const textChunk = JSON.parse(event.data);
                fullMarkdownContent += textChunk;

                // 2. On convertit le contenu TOTAL en HTML et on met à jour le DOM
                // La conversion du tout garantit que les blocs Markdown (listes, code) sont toujours corrects.
                botMessageElement.innerHTML = marked.parse(fullMarkdownContent);
                chatMessages.scrollTop = chatMessages.scrollHeight;
            };

            // 3. S'exécute lorsque le flux se termine (ou en cas d'erreur)
            eventSource.onerror = (error) => {
                console.error("EventSource failed:", error);
                const finalContent = truncateGracefully(fullMarkdownContent);
                botMessageElement.innerHTML = marked.parse(finalContent);
                chatMessages.scrollTop = chatMessages.scrollHeight;
                eventSource.close(); // On ferme la connexion
            };

        } catch (error) {
            console.error('Error:', error);
            botMessageElement.innerText = 'Désolé, une erreur est survenue.';
        }
    }

    function appendMessage(sender, message) {
        const messageElement = document.createElement('div');
        messageElement.classList.add('chatbot-message', sender + '-message');
        messageElement.innerText = message;
        chatMessages.appendChild(messageElement);
        chatMessages.scrollTop = chatMessages.scrollHeight;
        return messageElement;
    }

    function displaySolrResults(results) {
        if (!solrResultsContainer) {
            return;
        }
        solrResultsContainer.innerHTML = ''; // Clear previous results

        if (!results || results.length === 0) {
            solrResultsContainer.innerHTML = '<p>Aucun résultat trouvé.</p>';
            return;
        }

        results.forEach(result => {
            const resultElement = document.createElement('div');
            resultElement.classList.add('solr-result');

            const title = document.createElement('h3');
            const link = document.createElement('a');
            link.href = result.url; // Assuming there is a URL in the result
            link.textContent = result.title;
            title.appendChild(link);

            const content = document.createElement('p');
            // Substring to show a snippet, adjust length as needed
            content.textContent = result.content ? result.content.substring(0, 250) + '...' : '';

            resultElement.appendChild(title);
            resultElement.appendChild(content);

            solrResultsContainer.appendChild(resultElement);
        });
    }
});