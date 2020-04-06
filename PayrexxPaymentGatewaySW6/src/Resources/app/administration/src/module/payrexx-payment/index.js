import './extension/sw-settings-index';

import './page/payrexx-settings';

import deDE from './snippet/de_DE.json';
import enGB from './snippet/en_GB.json';


const { Module } = Shopware;

Module.register('payrexx-payment', {
    type: 'plugin',
    name: 'PayrexxPayment',
    title: 'payrexx-payment.module.title',
    description: 'payrexx-payment.module.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    icon: 'default-action-settings',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        index: {
            component: 'payrexx-settings',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index'
            }
        }
    }
});
