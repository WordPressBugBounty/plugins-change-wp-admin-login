<template>
		<input :id="id" :type="type" v-model="value" />
</template>

<script>
export default {
	name: 'aio-login-color-picker',

	props: {
		defaultValue: {
			type: String,
			default: '',
		},

		id: {
			type: String,
			required: true,
		}
	},

	data: ( vm ) => ( {
		value: vm.defaultValue,
	} ),

	watch: {
		value( newValue ) {
			this.$emit( 'color-changed', newValue );
		},
	},

	computed: {
		type() {
			if ( jQuery ) {
				return 'text';
			} else {
				return 'color';
			}
		}
	},

	mounted() {
		if ( jQuery ) {
			jQuery( this.$el ).wpColorPicker( {
				change: ( event, ui ) => {
					this.value = ui.color.toString();
				},
			} );
		}
	},
}
</script>

<style scoped>

</style>