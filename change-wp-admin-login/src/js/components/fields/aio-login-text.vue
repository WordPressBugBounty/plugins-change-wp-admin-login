<template>
		<input
			v-if="'textarea' !== type"
			class="regular-text"
			:id="id"
			:name="name"
			:value="modelValue"
			:type="type"
			:disabled="disabled"
			@input="$emit( 'update:modelValue', $event.target.value ); $emit( 'input', $event.target.value )"
		>

		<textarea
			v-else
			class="regular-text"
			:id="id"
			:name="name"
			:disabled="disabled"
		>{{ modelValue }}</textarea>
</template>

<script>
export default {
	name: 'aio-login-text',

	props: {
		type: {
			type: String,
			default: 'text',
		},
		id: {
			type: String,
			required: true,
		},
		name: {
			type: String,
			required: true,
		},
		modelValue: {
			type: [String, Number],
			default: '',
		},
		disabled: {
			type: Boolean,
			default: false,
		},
	},
}
</script>

<style scoped>

</style>