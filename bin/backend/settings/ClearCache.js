/**
 * @module package/quiqqer/cache/bin/backend/settings/ClearCache
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/cache/bin/backend/settings/ClearCache', [

    'qui/QUI',
    'qui/controls/Control',
    'Ajax',
    'Locale'

], function (QUI, QUIControl, QUIAjax, QUILocale) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/cache/bin/backend/settings/ClearCache',

        Binds: [
            '$onImport',
            'clearCache'
        ],

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onImport: this.$onImport
            });
        },

        $onImport: function () {
            this.$InputHidden = this.getElm();
            this.$Container   = new Element('div', {
                'class': 'field-container-field'
            });

            this.$Container.wraps(this.$InputHidden);
            this.$Button = new Element('button', {
                'class': 'qui-button qui-utils-noselect',
                html   : QUILocale.get('quiqqer/cache', 'clear.cache.button'),
                events : {
                    click: this.clearCache
                }
            }).inject(this.$Container);
        },

        clearCache: function () {
            this.$Button.disabled = true;
            this.$Button.setStyle('width', this.$Button.getSize().x);
            this.$Button.set('html', '<span class="fa fa-spinner fa-spin"></span>');

            QUIAjax.post('package_quiqqer_cache_ajax_backend_clearCache', function () {
                this.$Button.disabled = false;
                this.$Button.setStyle('width', null);
                this.$Button.set('html', QUILocale.get('quiqqer/cache', 'clear.cache.button'));
            }.bind(this), {
                'package': 'quiqqer/cache'
            });
        }
    });
});
