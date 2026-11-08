import '../css/field.css';
import DetailField from './components/DetailField.vue';
import FormField from './components/FormField.vue';

Nova.booting((app) => {
    app.component('detail-specific-days', DetailField);
    app.component('form-specific-days', FormField);
});
