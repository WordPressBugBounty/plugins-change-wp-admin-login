/**
 * Shared Vue runtime for Free + Pro admin apps.
 * Pro externalizes `vue` to this global so route SFCs resolve Free-registered components.
 */
window.vue = require( 'vue' );
window.vueRouter = require( 'vue-router' );
