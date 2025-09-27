document.addEventListener('DOMContentLoaded', function () {
    const fab = document.getElementById('w3c-ai-chatbot-fab');
    const chatWindow = document.getElementById('w3c-ai-chat-wrapper');
    const chatHeader = document.querySelector('.w3c-ai-chat-header');
    const localizationContainer = document.getElementById('w3c-aichatbot-localization');

    const localization = {
        loading: localizationContainer.dataset.loading,
        errorMessage: localizationContainer.dataset.errorMessage,
        yourOpinion: localizationContainer.dataset.yourOpinion,
        thankYou: localizationContainer.dataset.thankYou,
        noResults: localizationContainer.dataset.noResults
    };

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
    } else {
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
        if (text.slice(-1) !== "\n") {
            const lastNewlinePos = text.lastIndexOf("\n");
            if (lastNewlinePos !== -1) {
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

        const loadingElement = appendMessage('bot', localization.loading);

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
            loadingElement.innerHTML = data.answer;
            appendRatingUI(loadingElement, question, data.answer);
            if (solrResultsContainer) {
                displaySolrResults(data.solrResults);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            loadingElement.innerText = localization.errorMessage;
        });
    }

    async function sendMessageStream() {
        const question = chatInput.value;
        if (question.trim() === '') {
            return;
        }

        appendMessage('user', question);
        chatInput.value = '';

        const botMessageElement = appendMessage('bot', localization.loading);
        let fullMarkdownContent = '';

        const url = new URL(`/index.php?id=${pageId}&type=2999`, window.location.origin);
        url.searchParams.append('tx_w3caichatbot_chatbotajax[question]', question);
        url.searchParams.append('tx_w3caichatbot_chatbotajax[action]', 'askStream');
        url.searchParams.append('tx_w3caichatbot_chatbotajax[controller]', 'Chatbot');

        try {
            const eventSource = new EventSource(url.toString());

            eventSource.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    if (typeof data === 'string') {
                        fullMarkdownContent += data;
                        botMessageElement.innerHTML = marked.parse(fullMarkdownContent);
                        chatMessages.scrollTop = chatMessages.scrollHeight;
                    }
                } catch (e) {
                    console.log("Could not parse message data, probably end of stream or custom event.", event.data);
                }
            };

            eventSource.onerror = (error) => {
                console.error("EventSource failed:", error);
                const finalContent = truncateGracefully(fullMarkdownContent);
                botMessageElement.innerHTML = marked.parse(finalContent);
                appendRatingUI(botMessageElement, question, finalContent);
                chatMessages.scrollTop = chatMessages.scrollHeight;
                eventSource.close();
            };

        } catch (error) {
            console.error('Error:', error);
            botMessageElement.innerText = localization.errorMessage;
        }
    }

    function appendMessage(sender, message) {
        const messageElement = document.createElement('div');
        messageElement.classList.add('chatbot-message', sender + '-message');
        if (sender === 'bot') {
            messageElement.innerHTML = message;
        } else {
            messageElement.innerText = message;
        }
        chatMessages.appendChild(messageElement);
        chatMessages.scrollTop = chatMessages.scrollHeight;
        return messageElement;
    }

    function appendRatingUI(messageElement, question, answer) {
        const ratingContainer = document.createElement('div');
        ratingContainer.classList.add('rating-container');
        ratingContainer.innerHTML = localization.yourOpinion;

        for (let i = 1; i <= 5; i++) {
            const star = document.createElement('span');
            star.classList.add('rating-star');
            star.innerHTML = '&#9733;';
            star.dataset.rating = i;
            star.addEventListener('click', () => {
                rateAnswer(question, answer, i, ratingContainer);
            });
            ratingContainer.appendChild(star);
        }
        messageElement.appendChild(ratingContainer);
    }

    function rateAnswer(question, answer, rating, ratingContainer) {
        const formData = new FormData();
        formData.append('tx_w3caichatbot_chatbotajax[question]', question);
        formData.append('tx_w3caichatbot_chatbotajax[answer]', answer);
        formData.append('tx_w3caichatbot_chatbotajax[rating]', rating);
        formData.append('tx_w3caichatbot_chatbotajax[action]', 'rate');
        formData.append('tx_w3caichatbot_chatbotajax[controller]', 'Chatbot');

        fetch(`/index.php?id=${pageId}&type=2999`, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                ratingContainer.innerHTML = localization.thankYou;
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    }

    function displaySolrResults(results) {
        if (!solrResultsContainer) {
            return;
        }
        solrResultsContainer.innerHTML = '';

        if (!results || results.length === 0) {
            solrResultsContainer.innerHTML = `<p>${localization.noResults}</p>`;
            return;
        }

        results.forEach(result => {
            const resultElement = document.createElement('div');
            resultElement.classList.add('solr-result');

            const title = document.createElement('h3');
            const link = document.createElement('a');
            link.href = result.url;
            link.textContent = result.title;
            title.appendChild(link);

            const content = document.createElement('p');
            content.textContent = result.content ? result.content.substring(0, 250) + '...' : '';

            resultElement.appendChild(title);
            resultElement.appendChild(content);

            solrResultsContainer.appendChild(resultElement);
        });
    }
});
