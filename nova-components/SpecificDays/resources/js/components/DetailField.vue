<template>
    <PanelItem :index="index" :field="field" />
</template>

<script>
/*
 * The days as the resource writes them out (its displayUsing), in Nova's
 * own detail row.
 */
export default {
    props: ['index', 'resource', 'resourceName', 'resourceId', 'field'],
};
</script>
