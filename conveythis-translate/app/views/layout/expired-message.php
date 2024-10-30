<?php if (!empty($this->api_key) && $this->checkCachePlugin() && !$this->isDismiss('all_cache_notice')){ ?>
    <div class="row">
        <div class="col-md-12">
            <div class="alert alert-warning" id="conveythis_confirmation_message_clear_all_cahce"
                 role="alert"
                 style="border: #ffecb5 2px solid;color: #000;padding-left: 10px;background: #fff;">
                <p>You have a cache plugin installed. If you don't see ConveyThis widget on your pages, you can reset your site cache.</p>
                <div class="text-left">
                    <button id="conveythis_clear_all_cache" type="button" class="btn btn-danger"
                            data-href="<?php echo esc_url(admin_url('admin-ajax.php') . '?action=conveythis_clear_all_cache'); ?>">Clear all cache
                    </button>
                    <button id="conveythis_dismiss_all_cache" type="button" class="btn btn btn-light"
                            data-href="<?php echo esc_url(admin_url('admin-ajax.php') . '?action=conveythis_dismiss_all_cache'); ?>">Dismiss this notice
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php } ?>