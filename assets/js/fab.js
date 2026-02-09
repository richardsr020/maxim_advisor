// fab.js - Bouton flottant déplaçable

document.addEventListener('DOMContentLoaded', () => {
    const fab = document.querySelector('.chat-fab');
    if (!fab) return;

    const clamp = (value, min, max) => Math.min(Math.max(value, min), max);
    const getBounds = () => fab.getBoundingClientRect();

    const applyPosition = (x, y) => {
        const width = fab.offsetWidth || 56;
        const height = fab.offsetHeight || 56;
        const maxX = Math.max(6, window.innerWidth - width - 6);
        const maxY = Math.max(6, window.innerHeight - height - 6);
        const nextX = clamp(x, 6, maxX);
        const nextY = clamp(y, 6, maxY);
        fab.style.left = `${nextX}px`;
        fab.style.top = `${nextY}px`;
        fab.style.right = 'auto';
        fab.style.bottom = 'auto';
    };

    const stored = localStorage.getItem('chat_fab_position');
    if (stored) {
        try {
            const pos = JSON.parse(stored);
            if (typeof pos.x === 'number' && typeof pos.y === 'number') {
                requestAnimationFrame(() => applyPosition(pos.x, pos.y));
            }
        } catch (e) {
            // ignore invalid storage
        }
    }

    let pointerId = null;
    let startX = 0;
    let startY = 0;
    let originX = 0;
    let originY = 0;
    let dragged = false;

    const normalizePosition = () => {
        if (!fab.style.left || !fab.style.top) return;
        const rect = getBounds();
        applyPosition(rect.left, rect.top);
    };

    fab.addEventListener('pointerdown', (event) => {
        pointerId = event.pointerId;
        fab.setPointerCapture(pointerId);
        const rect = getBounds();
        startX = event.clientX;
        startY = event.clientY;
        originX = rect.left;
        originY = rect.top;
        dragged = false;
    });

    fab.addEventListener('pointermove', (event) => {
        if (pointerId !== event.pointerId) return;
        const dx = event.clientX - startX;
        const dy = event.clientY - startY;
        if (!dragged && Math.hypot(dx, dy) > 6) {
            dragged = true;
        }
        if (!dragged) return;

        applyPosition(originX + dx, originY + dy);
    });

    fab.addEventListener('pointerup', (event) => {
        if (pointerId !== event.pointerId) return;
        fab.releasePointerCapture(pointerId);

        if (dragged) {
            fab.dataset.dragged = '1';
            const rect = getBounds();
            localStorage.setItem('chat_fab_position', JSON.stringify({ x: rect.left, y: rect.top }));
        }

        pointerId = null;
        setTimeout(() => {
            fab.dataset.dragged = '0';
        }, 0);
    });

    fab.addEventListener('click', (event) => {
        if (fab.dataset.dragged === '1') {
            event.preventDefault();
            event.stopPropagation();
        }
    });

    window.addEventListener('resize', () => {
        normalizePosition();
    });
});
