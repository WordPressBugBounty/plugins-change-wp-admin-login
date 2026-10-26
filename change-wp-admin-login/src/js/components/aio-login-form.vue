<template>
	<div>
		<form :action="ajaxurl" method="post" @submit="submitHandler">
			<h2 class="aio-login-form-title">
				<slot name="title"></slot>
			</h2>
			<p class="description">
				<strong>
					<slot name="description"></slot>
				</strong>
			</p>
			<input type="hidden" name="_wpnonce" :value="action">

			<table class="form-table">
				<tbody>
					<slot name="form-fields"></slot>
				</tbody>
			</table>

			<aio-login-submit-button :disabled="disabled" />
		</form>
	</div>
</template>

<script>
export default {
	name: 'aio-login-form',
	props: {
		action: {
			type: String,
			required: true,
		},
		disabled: {
			type: Boolean,
			default: false,
		},
	},

	data: () => ( {
		ajaxurl: ajaxurl,
	} ),

	methods: {
		submitHandler( e ) {
			e.preventDefault();
			if ( this.disabled ) {
				return;
			}
			this.$emit( 'handle-submit', e );
		},
	},

	mounted() {

	},
}
</script>

<style scoped>
.aio-login-form-title {
	display: flex;
	align-items: center;
	flex-wrap: wrap;
	gap: 8px;
}

:deep(.aio-login-network-tag-wrap) {
	margin-left: auto;
}
</style>