import '../css/field.css';
import DetailField from './components/DetailField.vue';
import FormField from './components/FormField.vue';

Nova.booting((app) => {
    app.component('detail-video-embed', DetailField);
    app.component('form-video-embed', FormField);
});
