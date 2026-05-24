document.querySelectorAll('[data-copy]').forEach((button) => {
    button.addEventListener('click', async () => {
        const value = button.getAttribute('data-copy') || '';
        try {
            await navigator.clipboard.writeText(value);
            const oldText = button.textContent;
            button.textContent = 'Copied';
            setTimeout(() => { button.textContent = oldText; }, 1200);
        } catch (error) {
            window.prompt('Copy this URL:', value);
        }
    });
});
