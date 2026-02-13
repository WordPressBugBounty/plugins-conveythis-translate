<style>
    /* Fix vertical alignment of selected languages in Semantic UI dropdown */
    .ui.dropdown .label {
        display: inline-flex !important;
        align-items: center !important;
        line-height: 1.2;
        padding-top: 2px !important;
        padding-bottom: 2px !important;
    }

    .ui.dropdown .label > .delete.icon {
        margin-left: 0px !important;
        align-self: center !important;
        position: relative;
        top: -2.7px;
    }

</style>

<div class="tab-pane fade show active" id="v-pills-main" role="tabpanel" aria-labelledby="main-tab">

    <div class="title">Main configuration</div>
    <div class="alert alert-danger" id="conveythis_confirmation_message_danger" role="alert" style="display: none;border: #ce1717 2px solid;color: #000;padding-left: 10px;background: #fff;">
        We're sorry, you haven't verified your account. Follow the link in your email <span style="display: inline-block;"><b></b></span>
    </div>
    <div class="alert alert-danger" id="conveythis_trial_finished" role="alert" style="display: none;border: #ce1717 2px solid;color: #000;padding-left: 10px;background: #fff;">
        Your PRO trial has ended, and our widget on your site is currently inactive.<br> To republish the widget, please visit your <a href="https://app.conveythis.com/">dashboard</a> and select a plan.
    </div>
    <div class="alert alert-warning" id="conveythis_trial_period" role="alert" style="display: none;border: #ffecb5 2px solid;color: #000;padding-left: 10px;background: #fff;">
        <span id="trial-days"></span><span id="trial-period"></span> left of the PRO trial.<br> Your free PRO trial is coming to an end. Click <a href="https://app.conveythis.com/dashboard/pricing/">here</a> to upgrade your plan.
    </div>
    <div class="alert alert-warning" id="conveythis_confirmation_message_warning" role="alert" style="display: none;border: #ffecb5 2px solid;color: #000;padding-left: 10px;background: #fff;">
        Your account is not verified, you can use the plugin until <span></span> <br/>
<!--        After verifying your email, you will receive your account credentials and can then log in to our <a href="--><?php //echo esc_url(CONVEYTHIS_APP_URL . '/account/login/')?><!--" target="_blank">website</a>.-->
    </div>

<!--    --><?php //if ($this->variables->is_translated == '0' && !empty($this->variables->target_languages)):?>
<!---->
<!--        <div class="alert alert-warning" role="alert" style="display: block;border: #ffecb5 2px solid;color: #000;padding-left: 10px;background: #fff;">-->
<!--            Once you receive your first page translation on your <a href="--><?php //echo esc_url(home_url());?><!--">site</a>, you'll gain access to all the settings of our plugin. Simply <a id="refresh" href="#">refresh</a> this page.-->
<!--        </div>-->
<!--   -->
<!--    --><?php //endif;?>

    <?php if (empty($this->variables->target_languages)):?>
        <div class="alert alert-warning" role="alert" style="display: block;border: #ffecb5 2px solid;color: #000;padding-left: 10px;background: #fff;">
            Please select your source and target languages at this stage.
        </div>
    <?php endif;?>

    <div class="alert alert-danger" id="conveythis_word_translation_exceeded_warning" role="alert" style="display: none;border: #f5c2c7 2px solid;color: #000;padding-left: 10px;background: #fff;">
        Your translation word limit has been exceeded. Please <a href="https://app.conveythis.com/dashboard/pricing/" target="_blank">upgrade</a> your plan. <span></span>
    </div>
    <div class="alert alert-danger" id="conveythis_views_limit_exceeded_warning" role="alert" style="display: none;border: #f5c2c7 2px solid;color: #000;padding-left: 10px;background: #fff;">
        Your page view limit has been exceeded. Please <a href="https://app.conveythis.com/dashboard/pricing/" target="_blank">upgrade</a> your plan. <span></span>
    </div>
    <div class="alert alert-danger" id="conveythis_languages_limit_exceeded_warning" role="alert" style="display: none;border: #f5c2c7 2px solid;color: #000;padding-left: 10px;background: #fff;">
        Your languages limit has been exceeded, please <a href="https://app.conveythis.com/dashboard/pricing/" target="_blank">upgrade</a> your plan. <span></span>
    </div>

    <div class="form-group" >
        <div class="subtitle">My translations</div>
        <div>
            <?php
            $edit_translations_url = "https://app.conveythis.com/dashboard/";
            if(!empty($this->variables->domain_id)){
                $edit_translations_url = "https://app.conveythis.com/dashboard/translation/domain/" . $this->variables->domain_id . "/";
            }
            ?>
            You can find your translations in your ConveyThis dashboard: <a href="<?php echo $edit_translations_url; ?>" target="_blank" class="btn btn-primary btn-sm">Edit translations</a>
        </div>
    </div>


    <div class="form-group" id="apiKey">
        <div class="subtitle">Api Key</div>
        <div class="ui input w-100">
            <input type="text" name="api_key" id="conveythis_api_key" class="conveythis-input-text text-truncate" value="<?php echo  esc_attr( $this->variables->api_key ); ?>" placeholder="pub_XXXXXXXXXXXXXXXX" />
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-exclamation-circle" viewBox="0 0 16 16">
                <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                <path d="M7.002 11a1 1 0 1 1 2 0 1 1 0 0 1-2 0zM7.1 4.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 4.995z"/>
            </svg>
        </div>
        <label class="validation-label">This field is required</label>
    </div>

    <div class="form-group" id="sourceLanguage">
        <div class="subtitle">Source Language</div>
        <label for="">What is the source (current) language of your website?</label>
        <div class="ui dropdown fluid search selection widget-trigger dropdown-current-language">
            <input type="hidden" name="source_language" value="<?php echo esc_html($this->variables->source_language); ?>">
            <i class="dropdown icon"></i>
            <div class="default text"><?php echo  esc_html(__( 'Select source language', 'conveythis-translate' )); ?></div>
            <div class="menu">

                <?php foreach( $this->variables->languages as $language ): ?>

                    <div class="item" data-value="<?php echo  esc_attr( $language['code2'] ); ?>">
                        <?php echo esc_html( $language['title_en'], 'conveythis-translate' ); ?>
                    </div>

                <?php endforeach; ?>

            </div>
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-exclamation-circle" viewBox="0 0 16 16">
                <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                <path d="M7.002 11a1 1 0 1 1 2 0 1 1 0 0 1-2 0zM7.1 4.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 4.995z"/>
            </svg>
        </div>
        <label class="validation-label">This field is required</label>
    </div>
    <div class="form-group" id="targetLanguages">
        <div class="subtitle">Target Languages</div>
        <label for="">Choose languages you want to translate into.</label>
        <div class=" ui dropdown  fluid multiple search selection dropdown-target-languages widget-trigger">
            <input type="hidden" name="target_languages" value="<?php echo esc_attr(implode( ',', $this->variables->target_languages )); ?>">
            <i class="dropdown icon"></i>
            <div class="default text">French, German, Italian, Portuguese…</div>
            <div class="menu">

                <?php foreach ($this->variables->languages as $language): ?>

                    <div class="item target-language-<?php echo esc_attr($language['code2']); ?>" data-value="<?php echo esc_attr($language['code2']); ?>">
                        <?php echo esc_html($language['title_en'], 'conveythis-translate'); ?>
                    </div>

                <?php endforeach; ?>

            </div>
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-exclamation-circle" viewBox="0 0 16 16">
                <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                <path d="M7.002 11a1 1 0 1 1 2 0 1 1 0 0 1-2 0zM7.1 4.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 4.995z"/>
            </svg>
        </div>
        <label class="validation-label">This field is required</label>
        <label class="hide-paid" for="">On the free plan, you can only choose one target language.<br>
            If you want to use more than 1 language, please <a href="https://app.conveythis.com/dashboard/pricing/?utm_source=widget&utm_medium=wordpress" target="_blank" class="grey">upgrade your plan</a>.</label>
    </div>

</div>