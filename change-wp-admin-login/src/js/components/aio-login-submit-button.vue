<template>
	<div>
		<p class="submit">
			<button
				type="submit"
				:class="class"
				:disabled="disabled"
				@click="( e ) => $emit('button-click', e )">
			<slot>{{ $t('Save Changes') }}</slot></button>
		</p>
	</div>
</template>

<script>
export default {
	name: 'aio-login-submit-button',

	props: {
		type: {
			type: String,
			default: 'primary'
		},
		disabled: {
			type: Boolean,
			default: false,
		}
	},

	computed: {
		class() {
			return {
				'button': true,
				'aio-login__primary': this.type === 'primary',
				'button-secondary': this.type === 'secondary',
			};
		}
	}
}
</script>

<style scoped>
.aio-login__primary {
	border: 1px solid #9516df;
	background-color: #9416de;
	color: #fff;
}

.aio-login__primary:hover:not(:disabled) {
	background-color: #8310c7;
	color: #fff;
}

.aio-login__primary:disabled,
.aio-login__primary[disabled] {
	background-color: #9416de !important;
	color: #ffffff !important;
	opacity: 0.5 !important;
	border-color: #9416de !important;
	cursor: not-allowed !important;
	box-shadow: none !important;
	text-shadow: none !important;
}
</style>