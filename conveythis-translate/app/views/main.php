<div class="wrap">

    <?php require_once(CONVEY_PLUGIN_ROOT_PATH . 'app/views/layout/expired-message.php'); ?>

    <div class="settings-block">
        <form method="post" class="conveythis-widget-option-form w-100" id="conveythis-settings-form">
            <?php
            wp_nonce_field('conveythis_ajax_save', 'conveythis_nonce');
            settings_fields('my-plugin-settings-group');
            do_settings_sections('my-plugin-settings-group');
            ?>
            <div class="main-block">
                <!--Head block-->
                <div class="d-flex justify-content-between align-items-center">
                    <div class="">
                        <div>
                            <a href="https://www.conveythis.com/" target="_blank"><img src="<?php echo esc_url(CONVEY_PLUGIN_PATH); ?>app/widget/images/logo-convey.png" alt="ConveyThis"></a>
                        </div>
                    </div>
                    <div class="text-end text-dark d-none" id="plan_info">
                        <div>
                            <span>Your plan: </span> <a href="https://app.conveythis.com/dashboard/pricing/" target="_blank"><span id="plan_name" class="fw-bold"></span> </a>
                        </div>

                        <div id="trial_days_info" class="d-none alert alert-warning mt-1">
                            <span>
                                <span id="trial_days" class="fw-bold"></span>
                                 <span id="trial_days_message"></span>
                            </span>
                        </div>

                    </div>
                </div>
                <!--Separator-->
                <div class="line-grey"></div>

                <div id="settings_content">

                <?php require_once(CONVEY_PLUGIN_ROOT_PATH . 'app/views/layout/menu.php'); ?>

                <div class="row col-md-12">
                    <div class="col-md-8 tab-content" id="pills-tabContent">
                        <?php
                        require_once(CONVEY_PLUGIN_ROOT_PATH . 'app/views/page/main-configuration.php');
                        require_once(CONVEY_PLUGIN_ROOT_PATH . 'app/views/page/general-settings.php');
                        require_once(CONVEY_PLUGIN_ROOT_PATH . 'app/views/page/widget-style.php');
                        require_once(CONVEY_PLUGIN_ROOT_PATH . 'app/views/page/block-pages.php');
                        require_once(CONVEY_PLUGIN_ROOT_PATH . 'app/views/page/glossary.php');
                       // require_once(CONVEY_PLUGIN_ROOT_PATH . 'app/views/page/links.php');
                        require_once(CONVEY_PLUGIN_ROOT_PATH . 'app/views/page/plan.php');
                        require_once(CONVEY_PLUGIN_ROOT_PATH . 'app/views/page/cache.php');
                        ?>
                    </div>
                    <div class="col-md-4 router-widget">
                        <?php
                        require_once CONVEY_PLUGIN_ROOT_PATH . 'app/views/layout/widget.php';
                        ?>
                    </div>
                </div>
                <!--Separator-->
                <div class="line-grey mt-3 mb-3"></div>

                <div class="btn-box d-flex justify-content-start">
                    <!--Submit button-->
                    <input type="button" id="ajax-save-settings" class="btn btn-primary btn-custom autoSave" value="Save settings">
                </div>

                    </div>
                <?php
                // For Some reason it was not working with style.css file... It would load old styles at all times
                ?>
                <style>

                    #congrats-modal .modal-content {
                        border: none;
                        border-radius: 12px;
                        overflow: hidden;
                        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                        background: white;
                    }

                    #congrats-modal .modal-header {
                        border: none;
                        position: relative;
                        z-index: 2;
                    }

                    #congrats-modal .modal-body {
                        padding: 1rem 2rem 1rem;
                        position: relative;
                        z-index: 2;
                    }

                    #congrats-modal .modal-footer {
                        border: none;
                        padding: 1rem 2rem 1rem;
                        position: relative;
                        z-index: 2;
                    }

                    #congrats-modal .modal-title {
                        color: #144CAD;
                        font-weight: 700;
                        font-size: 1.75rem;
                        text-align: center;
                    }

                    #congrats-modal .modal-body p {
                        color: rgba(255, 255, 255, 0.95);
                        line-height: 1.6;
                        font-size: 1.05rem;
                    }

                    #congrats-modal .celebration-icon {
                        width: 70px;
                        height: 70px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        background: rgba(255, 255, 255, 0.2);
                        border-radius: 50%;
                    }

                    #congrats-modal .celebration-icon svg {
                        width: 50px;
                        height: 50px;
                        fill: #144CAD;

                    }

                    @keyframes congrats-pulse {
                        0%, 100% {
                            transform: scale(1);
                            box-shadow: 0 0 0 0 rgba(255, 255, 255, 0.4);
                        }
                        50% {
                            transform: scale(1.05);
                            box-shadow: 0 0 0 20px rgba(255, 255, 255, 0);
                        }
                    }

                    #congrats-modal .btn-primary {
                        padding: 8px 24px;
                    }


                    #congrats-modal .decorative-circle {
                        position: absolute;
                        border-radius: 50%;
                        background: rgba(255, 255, 255, 0.1);
                    }

                    #congrats-modal .circle-1 {
                        width: 200px;
                        height: 200px;
                        top: -50px;
                        right: -50px;
                        animation: congrats-float 6s ease-in-out infinite;
                    }

                    #congrats-modal .btn-close {
                        z-index: 1000;
                        cursor: pointer;
                    }

                    #congrats-modal .circle-2 {
                        width: 150px;
                        height: 150px;
                        bottom: -30px;
                        left: -30px;
                        animation: congrats-float 8s ease-in-out infinite reverse;
                    }

                    @keyframes congrats-float {
                        0%, 100% {
                            transform: translate(0, 0);
                        }
                        50% {
                            transform: translate(20px, 20px);
                        }
                    }

                    #congrats-modal.fade .modal-dialog {
                        transform: scale(0.7);
                        opacity: 0;
                        transition: transform 0.3s ease-out, opacity 0.3s ease-out;
                    }

                    #congrats-modal.show .modal-dialog {
                        transform: scale(1);
                        opacity: 1;
                    }
                </style>

                <div class="modal fade confetti" tabindex="-1" id="congrats-modal" role="dialog" aria-hidden="true" data-backdrop="static">
                    <div class="confetti-piece"></div>
                    <div class="confetti-piece"></div>
                    <div class="confetti-piece"></div>
                    <div class="confetti-piece"></div>
                    <div class="confetti-piece"></div>
                    <div class="confetti-piece"></div>
                    <div class="confetti-piece"></div>
                    <div class="confetti-piece"></div>
                    <div class="confetti-piece"></div>
                    <div class="confetti-piece"></div>
                    <div class="confetti-piece"></div>
                    <div class="confetti-piece"></div>
                    <div class="confetti-piece"></div>
                    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" role="document">
                        <div class="modal-content bg-light py-3">
                            <div class="decorative-circle circle-1"></div>
                            <div class="decorative-circle circle-2"></div>

                            <div style="cursor: pointer;" class="btn-close position-absolute top-0 end-0 m-3 " data-dismiss="modal" aria-label="Close" onclick="closeModal()"></div>

                            <div class="modal-header d-flex justify-content-center">
                                <div class="d-flex align-items-center">

                                    <div class="celebration-icon">
                                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="28" height="28">
                                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"></path>
                                        </svg>
                                    </div>

                                </div>
                            </div>
                            <h5 class="modal-title text-center" id="exampleModalLabel">Your website is multilingual now!</h5>

                            <div class="modal-footer d-flex justify-content-center">
                                <p style="color: #0f2942" class="fs-6 lead text-center pb-3">
                                    <b>Visit your webpage</b> to find our widget in the <b>lower right corner</b>. Click on different languages to see it in action!
                                </p><button type="button" id="visitsite" class="btn btn-primary pe-3 ps-3">Visit Site</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <div class="my-5" style="font-size: 14px">
        <a href="https://wordpress.org/support/plugin/conveythis-translate/reviews/#postform" target="_blank"> Love ConveyThis? Give us 5 stars on WordPress.org </a>
        <br> If you need any help, you can email us at
        <a href="mailto:support@conveythis.com"> support@conveythis.com</a>. You can also check our
        <a href="https://www.conveythis.com/faqs/?utm_source=widget&utm_medium=wordpress" target="_blank">FAQ</a>
    </div>
</div>



<script>
    function closeModal(){
        const modal = document.getElementById('congrats-modal');
        modal.classList.remove('show');
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');

        // Remove backdrop
        const backdrop = document.querySelector('.modal-backdrop');
        if (backdrop) {
            backdrop.remove();
        }

        // Remove modal-open class from body
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
    }

    document.addEventListener('DOMContentLoaded', function () {
        let targetLanguages = <?php echo json_encode($this->variables->target_languages)?>;
        let is_translated = <?php echo esc_html(get_option('is_translated'))?>;

        console.log("prepare congratulations")
        console.log("targetLanguages:" + targetLanguages)
        console.log("is_translated:" + is_translated)

        if (targetLanguages.length !== 0 && is_translated === 0) {
            const modal = document.getElementById('congrats-modal');

            // Add Bootstrap modal classes
            modal.classList.add('fade');

            setTimeout(function () {
                // Show modal
                modal.style.display = 'block';
                modal.classList.add('show');
                modal.setAttribute('aria-hidden', 'false');

                // Add backdrop
                const backdrop = document.createElement('div');
                backdrop.className = 'modal-backdrop fade show';
                document.body.appendChild(backdrop);

                // Add modal-open class to body
                document.body.classList.add('modal-open');
            }, 2000);
        }

        // Handle Visit Site button
        const visitSiteBtn = document.getElementById('visitsite');
        if (visitSiteBtn) {
            visitSiteBtn.addEventListener('click', function (e) {
                window.open(<?php echo json_encode(esc_url(home_url()))?>, '_blank');
                closeModal();
            });
        }

        // Handle X button click
        const closeBtn = document.querySelector('.btn-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                closeModal();
            });
        }

        // Handle backdrop click (clicking outside modal)
        const modal = document.getElementById('congrats-modal');
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target.classList.contains('modal')) {
                    closeModal();
                }
            });
        }
    });
</script>