// chat.js - Interface chat IA

let currentThreadId = null;
let chatSidebar = null;
let chatOverlay = null;
let typingIndicator = null;
let isSending = false;

const MESSAGE_STAGGER_MS = 140;

function isSmallScreen() {
    return window.matchMedia('(max-width: 900px)').matches;
}

function closeSidebar() {
    if (!chatSidebar || !chatOverlay) return;
    chatSidebar.classList.remove('open');
    chatOverlay.classList.remove('active');
}

function openSidebar() {
    if (!chatSidebar || !chatOverlay) return;
    chatSidebar.classList.add('open');
    chatOverlay.classList.add('active');
}

function renderThreads(threads) {
    const list = document.getElementById('chat-thread-list');
    list.innerHTML = '';
    threads.forEach(thread => {
        const button = document.createElement('button');
        button.className = 'chat-thread-item' + (thread.id === currentThreadId ? ' active' : '');
        button.textContent = thread.title;
        button.onclick = () => loadThread(thread.id);
        list.appendChild(button);
    });
}

function scrollMessagesToBottom(behavior = 'auto') {
    const container = document.getElementById('chat-messages');
    if (!container) return;
    container.scrollTo({ top: container.scrollHeight, behavior });
}

function createMessageElement(role, html, options = {}) {
    const wrapper = document.createElement('div');
    wrapper.className = 'chat-message ' + role;
    if (options.isNew) {
        wrapper.classList.add('is-new');
    } else {
        wrapper.classList.add('is-static');
    }

    const bubble = document.createElement('div');
    bubble.className = 'chat-bubble';
    if (html) {
        bubble.innerHTML = html;
    }

    wrapper.appendChild(bubble);
    return { wrapper, bubble };
}

function renderMessages(messages) {
    const container = document.getElementById('chat-messages');
    container.innerHTML = '';

    messages.forEach(message => {
        const { wrapper } = createMessageElement(message.role, message.content_html || '', { isNew: false });
        container.appendChild(wrapper);
    });

    scrollMessagesToBottom();
}

function appendUserMessage(html) {
    const container = document.getElementById('chat-messages');
    const { wrapper } = createMessageElement('user', html, { isNew: true });
    container.appendChild(wrapper);
    scrollMessagesToBottom('smooth');
}

function appendAssistantMessageProgressive(html) {
    const container = document.getElementById('chat-messages');
    const { wrapper, bubble } = createMessageElement('assistant', '', { isNew: true });
    const template = document.createElement('template');
    template.innerHTML = html || '';
    const nodes = Array.from(template.content.childNodes);
    let delay = 0;

    nodes.forEach((node) => {
        if (node.nodeType === Node.TEXT_NODE) {
            const text = node.textContent ? node.textContent.trim() : '';
            if (!text) return;
            const p = document.createElement('p');
            p.textContent = text;
            p.classList.add('chat-chunk');
            p.style.animationDelay = `${delay}ms`;
            bubble.appendChild(p);
            delay += MESSAGE_STAGGER_MS;
            return;
        }

        if (node.nodeType === Node.ELEMENT_NODE) {
            const element = node.cloneNode(true);
            element.classList.add('chat-chunk');
            element.style.animationDelay = `${delay}ms`;
            bubble.appendChild(element);
            delay += MESSAGE_STAGGER_MS;
        }
    });

    if (!bubble.childNodes.length) {
        bubble.innerHTML = html || '<p>Aucune réponse disponible.</p>';
    }

    container.appendChild(wrapper);
    scrollMessagesToBottom('smooth');
}

function showTypingIndicator() {
    const container = document.getElementById('chat-messages');
    if (!container || typingIndicator) return;
    const { wrapper, bubble } = createMessageElement('assistant', '', { isNew: true });
    wrapper.classList.add('chat-typing');
    bubble.innerHTML = '<span class="typing-dots" aria-label="Réponse en cours"><span></span><span></span><span></span></span>';
    container.appendChild(wrapper);
    typingIndicator = wrapper;
    scrollMessagesToBottom('smooth');
}

function hideTypingIndicator() {
    if (typingIndicator && typingIndicator.parentNode) {
        typingIndicator.parentNode.removeChild(typingIndicator);
    }
    typingIndicator = null;
}

function setFormLoading(isLoading) {
    const form = document.getElementById('chat-form');
    const textarea = document.getElementById('chat-message');
    const button = form ? form.querySelector('button[type="submit"]') : null;
    if (form) {
        form.classList.toggle('is-loading', isLoading);
        form.setAttribute('aria-busy', isLoading ? 'true' : 'false');
    }
    if (textarea) {
        textarea.disabled = isLoading;
    }
    if (button) {
        button.disabled = isLoading;
    }
}

function loadThreads() {
    fetch('/includes/chat.php?action=threads')
        .then(res => res.json())
        .then(data => {
            const threads = data.threads || [];
            if (!currentThreadId && threads.length > 0) {
                currentThreadId = threads[0].id;
                loadThread(currentThreadId);
            }
            if (threads.length === 0) {
                createThread();
                return;
            }
            renderThreads(threads);
        });
}

function createThread() {
    fetch('/includes/chat.php?action=create_thread', { method: 'POST' })
        .then(res => res.json())
        .then(data => {
            currentThreadId = data.thread_id;
            loadThreads();
            loadThread(currentThreadId);
        });
}

function loadThread(threadId) {
    currentThreadId = threadId;
    fetch('/includes/chat.php?action=messages&thread_id=' + encodeURIComponent(threadId))
        .then(res => res.json())
        .then(data => {
            renderMessages(data.messages || []);
            loadThreads();
            if (isSmallScreen()) {
                closeSidebar();
            }
        });
}

function sendMessage(message) {
    return fetch('/includes/chat.php?action=send', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            thread_id: currentThreadId,
            message
        })
    }).then(res => res.json());
}

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('chat-form');
    const textarea = document.getElementById('chat-message');
    const newThreadBtn = document.getElementById('new-thread-btn');
    chatSidebar = document.getElementById('chat-sidebar');
    chatOverlay = document.getElementById('chat-overlay');
    const toggleBtn = document.getElementById('chat-sidebar-toggle');
    const closeBtn = document.getElementById('chat-sidebar-close');

    newThreadBtn.addEventListener('click', () => createThread());

    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            if (chatSidebar.classList.contains('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', closeSidebar);
    }

    if (chatOverlay) {
        chatOverlay.addEventListener('click', closeSidebar);
    }

    window.addEventListener('resize', () => {
        if (!chatSidebar || !chatOverlay) return;
        if (!isSmallScreen()) {
            chatSidebar.classList.add('open');
            chatOverlay.classList.remove('active');
        } else {
            chatSidebar.classList.remove('open');
            chatOverlay.classList.remove('active');
        }
    });

    if (chatSidebar && !isSmallScreen()) {
        chatSidebar.classList.add('open');
    }

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        if (isSending) return;
        const message = textarea.value.trim();
        if (!message) return;
        const userMessage = {
            role: 'user',
            content_html: '<p>' + message.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</p>'
        };
        appendUserMessage(userMessage.content_html);

        textarea.value = '';
        isSending = true;
        setFormLoading(true);
        showTypingIndicator();

        sendMessage(message)
            .then(data => {
                hideTypingIndicator();
                if (data && data.assistant) {
                    appendAssistantMessageProgressive(data.assistant.content_html || '');
                } else {
                    appendAssistantMessageProgressive('<p>Désolé, je n’ai pas pu répondre pour le moment. Réessaie dans quelques instants.</p>');
                }
                loadThreads();
            })
            .catch(() => {
                hideTypingIndicator();
                appendAssistantMessageProgressive('<p>Une erreur est survenue. Réessaie dans un instant.</p>');
            })
            .finally(() => {
                isSending = false;
                setFormLoading(false);
            });
    });

    loadThreads();
});
