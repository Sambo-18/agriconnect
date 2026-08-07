/**
 * App JavaScript General Utilities & Interactivity
 * Application AgriConnect
 */

document.addEventListener('DOMContentLoaded', function() {
    // 1. Mobile Menu Toggle
    const mobileBtn = document.getElementById('mobileMenuBtn');
    const navBarNav = document.getElementById('navbarNav');
    
    if (mobileBtn && navBarNav) {
        mobileBtn.addEventListener('click', function() {
            navBarNav.classList.toggle('show');
        });
    }

    // 2. Alert Dismissible Buttons
    const alertBtns = document.querySelectorAll('.alert .btn-close');
    alertBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            this.closest('.alert').remove();
        });
    });

    // 3. Dynamic Cart Item Quantity Multiplier Calculation
    const qtyInput = document.getElementById('quantite_commande');
    const unitPriceInput = document.getElementById('prix_unitaire_input');
    const totalDisplay = document.getElementById('total_commande_display');

    if (qtyInput && unitPriceInput && totalDisplay) {
        const calculateTotal = () => {
            const qty = parseFloat(qtyInput.value) || 0;
            const price = parseFloat(unitPriceInput.value) || 0;
            const total = qty * price;
            totalDisplay.textContent = new Intl.NumberFormat('fr-FR').format(total) + ' FCFA';
        };

        qtyInput.addEventListener('input', calculateTotal);
        calculateTotal();
    }
});
