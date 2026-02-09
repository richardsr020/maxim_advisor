<?php
// chat.php - UI Chat IA
require_once __DIR__ . '/../includes/period.php';
?>

<div class="chat-container" id="chat-container">
    <div class="chat-overlay" id="chat-overlay"></div>
    <div class="chat-sidebar" id="chat-sidebar">
        <div class="chat-sidebar-header">
            <div>
                <h2>💼 Agent IA Comptable</h2>
                <p class="stat-subtitle">Strict, rigoureux, pragmatique.</p>
            </div>
            <div class="chat-sidebar-actions">
                <button class="btn btn-secondary" id="new-thread-btn">Nouvelle discussion</button>
                <button class="chat-sidebar-close" id="chat-sidebar-close" type="button">×</button>
            </div>
        </div>
        <div class="chat-thread-list" id="chat-thread-list"></div>
    </div>

    <div class="chat-main">
        <div class="chat-main-header">
            <button class="chat-sidebar-toggle" id="chat-sidebar-toggle" type="button" aria-label="Ouvrir la liste des discussions">
                <i class="fas fa-bars"></i>
            </button>
            <div class="chat-main-title">Discussion</div>
        </div>
        <div class="chat-messages" id="chat-messages" role="log" aria-live="polite" aria-relevant="additions"></div>
        <form class="chat-input" id="chat-form">
            <textarea id="chat-message" placeholder="Posez votre question financière..." rows="2"></textarea>
            <button type="submit" class="btn btn-primary">Envoyer</button>
        </form>
    </div>
</div>

<script src="assets/js/chat.js"></script>
