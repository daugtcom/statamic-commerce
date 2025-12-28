import OrdersList from "./components/widgets/OrdersList.vue";

Statamic.booting(() => {
    Statamic.$components.register('OrdersList', OrdersList);
});
