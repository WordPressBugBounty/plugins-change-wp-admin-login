<template>
	<div v-if="page_loaded" class="aio-login-t-wrapper" @click="iWasTriggered">

		<div>
			<aio-login-form
				action="nonce"
			>
				<template v-slot:title>Templates</template>

				<template v-slot:form-fields>
					
					<tr>
						<th>
							<label for="templates">Templates</label>
						</th>
						<td>
							<div class="aio-login-pro__templates">

								<div v-for="template in templates">
									<label :for="template.id">
										<input type="radio" :id="template.id" name="template" :value="template.id" >
										<img :src="template.img" :alt="template.name">
									</label>
								</div>
								
							</div>
						</td>
					</tr>
					
				</template>
			</aio-login-form>
		</div>

		<div v-if="! hasPro" class="aio-login-t-content-overflow"></div>
	</div>
</template>

<script>
export default {
	name: 'aio-login-templates',

	slug: 'templates',

	props: {
		hasPro: {
			type: Boolean,
			default: false,
		},
	},

	data: ( vm ) => ( {
    assets_url: window.aio_login__app_object.assets_url,
		templates: [
			{
				id: 'default',
				img: window.aio_login__app_object.assets_url + 'images/templates/default.jpg',
				name: 'Default',
			},
			{
				id: 'template-1',
				img: window.aio_login__app_object.assets_url + 'images/templates/template-01.jpg',
				name: 'Template 1',
			},
			{
				id: 'template-2',
        img: window.aio_login__app_object.assets_url + 'images/templates/template-02.jpg',
				name: 'Template 2',
			},
		],

		page_loaded: false,
	} ),

	methods: {
		iWasTriggered() {

			this.$parent.$parent.$parent.popup = true;
		},

		loadComponent() {
			this.$nextTick( () => {
				this.page_loaded = true;
			} );
		},
	},

	mounted() {
		if ( ! this.hasPro ) {
			this.page_loaded = true;
		} else {
			this.loadComponent();
		}
	}
}
</script>

<style scoped>
.aio-login-t-wrapper {
	position: relative;
}

.aio-login-t-content-overflow {
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;

	backdrop-filter: blur(1px);
}

.aio-login-pro__templates {
	display: flex;
	flex-wrap: wrap;
}

.aio-login-pro__templates input[type="radio"] {
	display: none;
}

.aio-login-pro__templates img {
	width: 200px;
	height: auto;
	border: 2px solid transparent;
	margin: 10px;
	cursor: pointer;
}

.aio-login-pro__templates input:checked + img {
	border-color: #9416de;
}
</style>