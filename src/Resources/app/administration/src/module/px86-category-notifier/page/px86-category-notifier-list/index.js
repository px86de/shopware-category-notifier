import template from './px86-category-notifier-list.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('px86-category-notifier-list', {
    template,

    inject: [
        'repositoryFactory'
    ],

    data() {
        return {
            repository: null,
            subscriptions: null,
            isLoading: false,
            total: 0
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    computed: {
        columns() {
            return [
                {
                    property: 'email',
                    label: this.$tc('px86-category-notifier.list.columnEmail'),
                    allowResize: true,
                    primary: true
                },
                {
                    property: 'category.name',
                    label: this.$tc('px86-category-notifier.list.columnCategory'),
                    allowResize: true
                },
                {
                    property: 'firstName',
                    label: this.$tc('px86-category-notifier.list.columnFirstName'),
                    allowResize: true
                },
                {
                    property: 'lastName',
                    label: this.$tc('px86-category-notifier.list.columnLastName'),
                    allowResize: true
                },
                {
                    property: 'confirmed',
                    label: this.$tc('px86-category-notifier.list.columnConfirmed'),
                    allowResize: true
                },
                {
                    property: 'active',
                    label: this.$tc('px86-category-notifier.list.columnActive'),
                    allowResize: true
                },
                {
                    property: 'createdAt',
                    label: this.$tc('px86-category-notifier.list.columnCreatedAt'),
                    allowResize: true
                }
            ];
        }
    },

    created() {
        this.repository = this.repositoryFactory.create('px86_category_notifier_subscription');
        this.getList();
    },

    methods: {
        getList() {
            this.isLoading = true;

            const criteria = new Criteria();
            criteria.addAssociation('category');

            this.repository
                .search(criteria, Shopware.Context.api)
                .then((result) => {
                    this.subscriptions = result;
                    this.total = result.total;
                    this.isLoading = false;
                });
        },

        onChangeLanguage() {
            this.getList();
        }
    }
});
