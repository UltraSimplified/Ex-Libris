import '../css/field.css';
import DetailField from './components/DetailField.vue';
import FormField from './components/FormField.vue';
import IndexField from './components/IndexField.vue';

Nova.booting((app) => {
    app.component('index-gallery', IndexField);
    app.component('detail-gallery', DetailField);
    app.component('form-gallery', FormField);
});
