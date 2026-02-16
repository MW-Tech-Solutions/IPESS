async function submitPortalForm(form) {
    const feedback = form.querySelector('.portal-feedback');
    const submitButton = form.querySelector('button[type="submit"]');
    const originalText = submitButton ? submitButton.textContent : '';
    const refreshPage = form.getAttribute('data-refresh');

    try {
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = 'Saving...';
        }
        const response = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
        });
        const result = await response.json();

        if (!result.ok) {
            throw new Error(result.message || 'Action failed.');
        }

        if (feedback) {
            feedback.textContent = result.message;
            feedback.className = 'portal-feedback mt-2 text-success small';
        }

        if (refreshPage) {
            const content = document.getElementById('content');
            if (content) {
                const refreshed = await fetch(`pages/${refreshPage}.php`);
                const html = await refreshed.text();
                content.innerHTML = html;
            }
        }
    } catch (error) {
        if (feedback) {
            feedback.textContent = error.message;
            feedback.className = 'portal-feedback mt-2 text-danger small';
        }
    } finally {
        if (submitButton) {
            submitButton.textContent = originalText;
            submitButton.disabled = false;
        }
    }
}

document.addEventListener('submit', (event) => {
    if (event.target && event.target.matches('.portal-form')) {
        event.preventDefault();
        submitPortalForm(event.target);
    }
});
