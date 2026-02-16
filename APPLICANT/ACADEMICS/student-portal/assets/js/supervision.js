function bytesToHex(bytes) {
    return Array.from(new Uint8Array(bytes)).map(b => b.toString(16).padStart(2, '0')).join('');
}

async function hashFile(file) {
    const buffer = await file.arrayBuffer();
    const digest = await crypto.subtle.digest('SHA-256', buffer);
    return bytesToHex(digest);
}

function formatSize(size) {
    if (size > 1024 * 1024) {
        return `${(size / (1024 * 1024)).toFixed(2)} MB`;
    }
    if (size > 1024) {
        return `${(size / 1024).toFixed(1)} KB`;
    }
    return `${size} B`;
}

async function handleFileChange(input) {
    const form = input.closest('.chapter-upload');
    if (!form) return;

    const feedback = form.querySelector('.chapter-feedback');
    const submitButton = form.querySelector('.chapter-submit');
    const currentHash = form.getAttribute('data-current-hash') || '';

    if (!input.files || !input.files[0]) {
        if (feedback) {
            feedback.textContent = '';
        }
        if (submitButton) {
            submitButton.disabled = true;
        }
        return;
    }

    const file = input.files[0];
    const details = `Selected: ${file.name} (${formatSize(file.size)})`;

    try {
        const newHash = await hashFile(file);
        if (currentHash && newHash === currentHash) {
            if (feedback) {
                feedback.textContent = `${details}. No changes detected from last upload.`;
                feedback.className = 'chapter-feedback mt-2 text-warning small';
            }
            if (submitButton) {
                submitButton.disabled = true;
            }
            return;
        }

        if (feedback) {
            feedback.textContent = `${details}. Changes detected.`;
            feedback.className = 'chapter-feedback mt-2 text-success small';
        }
        if (submitButton) {
            submitButton.disabled = false;
        }
    } catch (error) {
        if (feedback) {
            feedback.textContent = `${details}. Unable to check changes.`;
            feedback.className = 'chapter-feedback mt-2 text-muted small';
        }
        if (submitButton) {
            submitButton.disabled = false;
        }
    }
}

async function submitRevision(form) {
    const submitButton = form.querySelector('.chapter-submit');
    const feedback = form.querySelector('.chapter-feedback');
    const originalText = submitButton ? submitButton.textContent : '';

    if (submitButton && submitButton.disabled) {
        return;
    }

    const data = new FormData(form);

    try {
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = 'Uploading...';
        }
        const response = await fetch(form.action, {
            method: 'POST',
            body: data,
        });
        const result = await response.json();

        if (!result.ok) {
            throw new Error(result.message || 'Upload failed.');
        }

        if (feedback) {
            feedback.textContent = result.message;
            feedback.className = 'chapter-feedback mt-2 text-success small';
        }

        const content = document.getElementById('content');
        if (content) {
            const refreshed = await fetch('pages/supervision.php');
            const html = await refreshed.text();
            content.innerHTML = html;
        }
    } catch (error) {
        if (feedback) {
            feedback.textContent = error.message;
            feedback.className = 'chapter-feedback mt-2 text-danger small';
        }
    } finally {
        if (submitButton) {
            submitButton.textContent = originalText;
            submitButton.disabled = false;
        }
    }
}

document.addEventListener('change', (event) => {
    if (event.target && event.target.matches('.chapter-upload input[type="file"]')) {
        handleFileChange(event.target);
    }
});

document.addEventListener('submit', (event) => {
    if (event.target && event.target.matches('.chapter-upload')) {
        event.preventDefault();
        submitRevision(event.target);
    }
});

async function submitSupervisionForm(form) {
    const feedback = form.querySelector('.supervision-feedback');
    const submitButton = form.querySelector('button[type="submit"]');
    const originalText = submitButton ? submitButton.textContent : '';

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
            feedback.className = 'supervision-feedback mt-2 text-success small';
        }

        const content = document.getElementById('content');
        if (content) {
            const refreshed = await fetch('pages/supervision.php');
            const html = await refreshed.text();
            content.innerHTML = html;
        }
    } catch (error) {
        if (feedback) {
            feedback.textContent = error.message;
            feedback.className = 'supervision-feedback mt-2 text-danger small';
        }
    } finally {
        if (submitButton) {
            submitButton.textContent = originalText;
            submitButton.disabled = false;
        }
    }
}

document.addEventListener('submit', (event) => {
    if (event.target && event.target.matches('.supervision-form')) {
        event.preventDefault();
        submitSupervisionForm(event.target);
    }
});

function scrollStudentChatToBottom() {
    const thread = document.getElementById('studentChatThread');
    if (!thread) return;
    thread.scrollTop = thread.scrollHeight;
}

document.addEventListener('DOMContentLoaded', () => {
    scrollStudentChatToBottom();
});

document.addEventListener('shown.bs.tab', (event) => {
    const target = event.target;
    if (target && target.id === 'communication-tab') {
        setTimeout(scrollStudentChatToBottom, 40);
    }
});
