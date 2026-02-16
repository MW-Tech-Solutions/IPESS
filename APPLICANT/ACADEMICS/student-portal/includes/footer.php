<?php $currentYear = date('Y'); ?>
<footer class="footer-jostum mt-4">
    <div class="container-fluid py-3">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
            <span>Joseph Sarwuan Tarka University, Makurdi</span>
            <span>&copy; <?php echo htmlspecialchars($currentYear, ENT_QUOTES, 'UTF-8'); ?> Postgraduate Portal</span>
        </div>
    </div>
</footer>

<div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="helpModalLabel">Help & Support</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="accordion" id="faqAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="faq-heading-1">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq-collapse-1" aria-expanded="true" aria-controls="faq-collapse-1">
                                How do I reset my password?
                            </button>
                        </h2>
                        <div id="faq-collapse-1" class="accordion-collapse collapse show" aria-labelledby="faq-heading-1" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                You can reset your password from the main login page by clicking the "Forgot Password" link.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="faq-heading-2">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq-collapse-2" aria-expanded="false" aria-controls="faq-collapse-2">
                                Where can I find my grades?
                            </button>
                        </h2>
                        <div id="faq-collapse-2" class="accordion-collapse collapse" aria-labelledby="faq-heading-2" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Your grades are available under the "Academics" > "Grades" section.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary">Live Chat with ICT</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
<script src="assets/js/supervision.js"></script>
<script src="assets/js/academics.js"></script>
<script src="assets/js/portal-forms.js"></script>
<script src="assets/js/topbar-submenu.js"></script>
