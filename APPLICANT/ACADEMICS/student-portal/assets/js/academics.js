async function submitAcademicsForm(form) {
    const feedback = form.querySelector('.academics-feedback');
    const submitButton = form.querySelector('button[type="submit"]');
    const originalText = submitButton ? submitButton.textContent : '';

    try {
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = 'Submitting...';
        }
        const response = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
        });
        const result = await response.json();

        if (!result.ok) {
            throw new Error(result.message || 'Request failed.');
        }

        if (feedback) {
            feedback.textContent = result.message;
            feedback.className = 'academics-feedback mt-2 text-success small';
        }

        const content = document.getElementById('content');
        if (content) {
            const refreshed = await fetch('pages/academics.php');
            const html = await refreshed.text();
            content.innerHTML = html;
        }
    } catch (error) {
        if (feedback) {
            feedback.textContent = error.message;
            feedback.className = 'academics-feedback mt-2 text-danger small';
        }
    } finally {
        if (submitButton) {
            submitButton.textContent = originalText;
            submitButton.disabled = false;
        }
    }
}

document.addEventListener('submit', (event) => {
    if (event.target && event.target.matches('.academics-form')) {
        event.preventDefault();
        submitAcademicsForm(event.target);
    }
});
