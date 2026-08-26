@php($settings = $block->decodedSettings())
<form data-appointments-form>
  <h2>{{ $settings['title'] ?? 'Book an appointment' }}</h2>
  <p>{{ $settings['intro'] ?? '' }}</p>
  <label>Service <select name="service_id"><option>Consultation</option></select></label>
  <label>Personnel <select name="resource_id"><option>Available person</option></select></label>
  <button type="submit">{{ $settings['submit_label'] ?? 'Book appointment' }}</button>
</form>
