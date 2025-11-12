// Import all necessary Storefront plugins
import CategoryNotifierPlugin from './category-notifier/category-notifier.plugin';

// Register your plugin via the existing PluginManager
const PluginManager = window.PluginManager;
PluginManager.register('CategoryNotifier', CategoryNotifierPlugin, '#category-notifier-form');
