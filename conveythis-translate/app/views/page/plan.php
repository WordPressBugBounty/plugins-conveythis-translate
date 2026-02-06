<div class="tab-pane fade" id="v-pills-plan" role="tabpanel" aria-labelledby="plan-tab">
    <div class="form-group paid-function">

        <div><span class="title">Current Plan </span><a target="_blank" href="https://app.conveythis.com/dashboard/pricing/">(More details)</a></div>

        <div class="alert alert-warning" id="conveythis_trial_period_tab" role="alert" style="display: none;border: #ffecb5 2px solid;color: #000;padding-left: 10px;background: #fff;">
            <span id="trial-days-tab"></span><span id="trial-period-tab"></span> left in the PRO trial.<br> Your PRO trial is coming to an end. Click <a target="_blank" href="https://app.conveythis.com/dashboard/pricing/">here</a> to upgrade your plan.
        </div>

        <div id="plan-info" class="mt-6">
            <p><span class="fs-6 text-dark">Current Plan:</span> <span class="fs-6 text-dark fw-bold" id="plan-name">Loading...</span></p>
            <p><span class="fs-6 text-dark">Available languages on plan:</span> <span class="fs-6 text-dark fw-bold" id="plan-languages">Loading...</span></p>
            <p><span class="fs-6 text-dark">Available words on plan:</span> <span class="fs-6 text-dark fw-bold" id="plan-words">Loading...</span></p>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                let planTab = document.querySelector('#plan-tab');
                if (planTab) {
                    planTab.addEventListener('shown.bs.tab', function () {
                        jQuery.ajax({
                            url: "https://api.conveythis.com/admin/account/plan/api-key/<?= esc_html($this->variables->api_key) ?>/",
                            success: function (result) {
                                jQuery('#plan-name').text(result.data.meta.title || 'N/A');
                                jQuery('#plan-languages').text(result.data.meta.languages || 'N/A');
                                jQuery('#plan-words').text(result.data.meta.words || 'N/A');
                            },
                            error: function () {
                                jQuery('#plan-name').text('Error');
                                jQuery('#plan-languages').text('Error');
                                jQuery('#plan-words').text('Error');
                            }
                        })
                    })
                }
            });
        </script>
    </div>
</div>