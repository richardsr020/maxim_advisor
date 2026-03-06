    </div> <!-- .app-container -->

    <a href="/?page=chat" class="chat-fab" title="Chat IA">
        <i class="fas fa-comments"></i>
    </a>
    
    <!-- Modals globaux -->
    <div id="global-modals">
        <!-- Modal d'ajout de revenu -->
        <div id="add-income-modal" class="modal" style="display: none;">
            <div class="modal-content">
                <form method="POST" action="?page=dashboard">
                    <h3>💰 Ajouter un revenu</h3>
                    
                    <div class="form-group">
                        <label>Type de revenu</label>
                        <select name="income_type" id="income-type" required>
                            <option value="main">Revenu principal (démarre nouvelle période)</option>
                            <option value="extra">Revenu occasionnel</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Montant (FC)</label>
                        <input type="number" name="amount" required min="1" step="1">
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <input type="text" name="description" required>
                    </div>

                    <div class="form-group compact-checkbox" id="extra-income-options" style="display: none;">
                        <label for="extra-income-to-savings-only">
                            <input type="checkbox" name="extra_income_to_savings_only" value="1" id="extra-income-to-savings-only">
                            <span>Revenu occasionnel: tout allouer à l'épargne (hors dîme)</span>
                        </label>
                    </div>
                    
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('add-income-modal')">
                            Annuler
                        </button>
                        <button type="submit" name="add_income" class="btn btn-primary">
                            Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div id="spend-saving-modal" class="modal" style="display: none;">
            <div class="modal-content">
                <form method="POST" action="?page=dashboard">
                    <h3>🎯 Utiliser l'épargne (projet)</h3>

                    <div class="form-group">
                        <label>Montant (FC)</label>
                        <input type="number" name="amount" required min="1" step="1">
                    </div>

                    <div class="form-group">
                        <label>Projet / motif</label>
                        <input type="text" name="description" required maxlength="140">
                    </div>

                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('spend-saving-modal')">
                            Annuler
                        </button>
                        <button type="submit" name="spend_saving" class="btn btn-primary">
                            Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div id="pay-tithing-modal" class="modal" style="display: none;">
            <div class="modal-content">
                <form method="POST" action="?page=dashboard">
                    <h3>⛪ Verser la dîme</h3>

                    <div class="form-group">
                        <label>Montant (FC)</label>
                        <input type="number" name="amount" required min="1" step="1">
                    </div>

                    <div class="form-group">
                        <label>Description</label>
                        <input type="text" name="description" value="Versement église" required maxlength="140">
                    </div>

                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('pay-tithing-modal')">
                            Annuler
                        </button>
                        <button type="submit" name="pay_tithing" class="btn btn-primary">
                            Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Modal d'ajout de dépense -->
        <div id="add-expense-modal" class="modal" style="display: none;">
            <div class="modal-content">
                <form method="POST" action="?page=dashboard">
                    <h3>💸 Ajouter une dépense</h3>
                    
                    <div class="form-group">
                        <label>Catégorie</label>
                        <select name="category_id" id="expense-category" required>
                            <option value="">Choisir une catégorie</option>
                            <?php
                            $categories = getAllCategories();
                            foreach ($categories as $cat):
                            ?>
                            <option value="<?php echo $cat['id']; ?>" 
                                    data-unexpected="<?php echo $cat['is_unexpected']; ?>">
                                <?php echo $cat['icon']; ?> <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Montant (FC)</label>
                        <input type="number" name="amount" required min="1" step="1">
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <input type="text" name="description" required>
                    </div>
                    
                    <div class="form-group" id="expense-comment-field" style="display: none;">
                        <label>Commentaire (obligatoire pour les imprévus)</label>
                        <textarea name="comment" rows="2"></textarea>
                    </div>
                    
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('add-expense-modal')">
                            Annuler
                        </button>
                        <button type="submit" name="add_expense" class="btn btn-primary">
                            Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="assets/js/app.js"></script>
    <?php
    require_once ROOT_PATH . '/includes/flash.php';
    $flashMessages = consumeFlashMessages();
    ?>
    <script>
        (function() {
            const messages = <?php echo json_encode($flashMessages, JSON_UNESCAPED_UNICODE); ?>;
            if (!Array.isArray(messages) || typeof showToast !== 'function') {
                return;
            }
            messages.forEach((toast) => {
                if (!toast || !toast.message) return;
                showToast(toast.message, toast.type || 'info');
            });
        })();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/js/charts.js"></script>
    <script src="assets/js/stats.js"></script>
    <script src="assets/js/fab.js"></script>
</body>
</html>
