document.addEventListener('DOMContentLoaded', function () {
    const chatbotContainer = document.getElementById('chatbot-container');
    if (!chatbotContainer) {
        return;
    }
    const pageId = chatbotContainer.dataset.pageId;
    const sendButton = document.getElementById('chatbot-send');
    const chatInput = document.getElementById('chatbot-input');
    const chatMessages = document.getElementById('chatbot-messages');
    const solrResultsContainer = document.getElementById('solr-results');

    sendButton.addEventListener('click', sendMessage);
    chatInput.addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            sendMessage();
        }
    });

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

        fetch(`/index.php?id=${pageId}&type=999`, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            // Update bot message with actual answer
            loadingElement.innerHTML = data.answer;
            displaySolrResults(data.solrResults);
        })
        .catch(error => {
            console.error('Error:', error);
            loadingElement.innerText = 'Sorry, something went wrong.';
        });
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