import './page/px86-category-notifier-list';

const { Module } = Shopware;

Module.register('px86-category-notifier', {
    type: 'plugin',
    name: 'CategoryNotifier',
    title: 'px86-category-notifier.general.mainMenuItemGeneral',
    description: 'px86-category-notifier.general.descriptionTextModule',
    color: '#ff3d58',
    icon: 'regular-bell',

    routes: {
        list: {
            component: 'px86-category-notifier-list',
            path: 'list'
        }
    },

    navigation: [{
        label: 'px86-category-notifier.general.mainMenuItemGeneral',
        color: '#ff3d58',
        path: 'px86.category.notifier.list',
        icon: 'regular-bell',
        parent: 'sw-marketing',
        position: 100
    }]
});
