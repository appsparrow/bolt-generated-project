document.addEventListener('DOMContentLoaded', () => {
    const playerList = document.getElementById('player-list');
    const setupForm = document.getElementById('setup-form');
    let draggedItem = null;

    // Add player function
    window.addPlayer = function() {
        const newPlayer = document.createElement('li');
        newPlayer.setAttribute('draggable', 'true');
        newPlayer.innerHTML = `
            <input type="text" name="players[]" required>
            <span class="drag-handle">☰</span>
            <button type="button" class="remove-player" onclick="removePlayer(this)">×</button>
        `;
        playerList.appendChild(newPlayer);
    };

    // Remove player function
    window.removePlayer = function(button) {
        const li = button.parentElement;
        // Prevent removing the last player
        if (playerList.children.length > 1) {
            li.remove();
        } else {
            alert('At least one player is required!');
        }
    };

    // Drag and drop functionality
    playerList.addEventListener('dragstart', (e) => {
        if (e.target.tagName === 'LI') {
            draggedItem = e.target;
            setTimeout(() => {
                e.target.classList.add('dragging');
                e.target.style.opacity = '0.5';
            }, 0);
        }
    });

    playerList.addEventListener('dragover', (e) => {
        e.preventDefault();
        const afterElement = getDragAfterElement(playerList, e.clientY);
        if (draggedItem) {
            if (afterElement) {
                playerList.insertBefore(draggedItem, afterElement);
            } else {
                playerList.appendChild(draggedItem);
            }
        }
    });

    playerList.addEventListener('dragend', (e) => {
        if (e.target.tagName === 'LI') {
            e.target.classList.remove('dragging');
            e.target.style.opacity = '1';
            draggedItem = null;
        }
    });

    function getDragAfterElement(container, y) {
        const draggableElements = [...container.querySelectorAll('li:not(.dragging)')];
        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > closest.offset) {
                return { offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }

    // Done button handler
    document.getElementById('done-button').addEventListener('click', () => {
        // Validate that all player names are filled
        const playerInputs = document.querySelectorAll('#player-list input');
        let allFilled = true;
        playerInputs.forEach(input => {
            if (!input.value.trim()) {
                allFilled = false;
            }
        });

        if (allFilled) {
            document.getElementById('done-button').style.display = 'none';
            document.getElementById('start-game-button').style.display = 'block';
        } else {
            alert('Please fill in all player names before proceeding.');
        }
    });

    // Form submission handler
    setupForm.addEventListener('submit', (e) => {
        const playerInputs = document.querySelectorAll('#player-list input');
        if (playerInputs.length < 1) {
            e.preventDefault();
            alert('At least one player is required!');
            return;
        }
    });
});
