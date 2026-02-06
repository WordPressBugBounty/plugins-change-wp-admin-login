<template>
	<div v-if="page_loaded">

		<aio-login-form :action="nonce" v-on:handle-submit="submitHandler">
			<template v-slot:title>
				Background Customization
			</template>
			<template v-slot:form-fields>
				<tr>
					<th scope="row">
						<label for="bg_color">Background Color</label>
					</th>
					<td>
						<aio-login-color-picker id="bg_color" :default-value="form_data.bg_color" v-on:color-changed="colorChanged" />
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="bg_image">Background Image</label>
					</th>
					<td>
						<aio-login-media
							id="bg_image"
							title="Upload Image"
							:image="bg_image"
							default-image=""
							v-on:image-updated="imageUpdated"
							v-on:image-removed="imageRemoved"
						/>
					</td>
				</tr>
			</template>
		</aio-login-form>

		<aio-login-snackbar
			:message="snackbar.message"
			v-if="snackbar.show"
			:duration="snackbar.duration"
			v-on:close="handleCloseSnackbar"
		/>

	</div>
</template>

<script>

export default {
	name: 'background',


	data: ( vm ) => ( {
		page_loaded: false,

		namespace: 'aio-login/background',

		nonce: '',

		form_data: {
			bg_image: '',
			bg_color: '',
		},

		bg_image: '',

		snackbar: {
			message: '',
			show: false,
			duration: 3000,
		},
	} ),

	methods: {
		colorChanged( newColor ) {
			this.form_data.bg_color = newColor;
		},

		imageUpdated( attachment ) {
			this.form_data.bg_image = attachment.id;
		},

		imageRemoved() {
			this.form_data.bg_image = 0;
		},

		submitHandler() {
			this.form_data['_wpnonce'] = this.nonce

			axios.post( this.namespace + '/save-settings', this.form_data )
				.then( response => {
					this.snackbar.message = response.data.message;
					this.snackbar.show = true;
				} )
				.catch( response => {

				} );
		},

		handleCloseSnackbar() {
			this.snackbar.show = false;
		},
	},

	mounted() {
		axios.get( this.namespace + '/get-settings' )
			.then( response => {

				this.form_data.bg_image = response.data.bg_image_id
				this.form_data.bg_color = response.data.bg_color
				this.bg_image           = response.data.bg_image;
				this.nonce              = response.data.nonce
				this.page_loaded        = true;
			} )
			.catch( response => {

			} );
	},
}
</script>

<style scoped>

</style>