<template>
	<div class="aio-login__snackbar">
		<div class="aio-login__snackbar__content">

			{{ message }}

			<button @click="handleClose">&times;</button>
		</div>
	</div>
</template>

<script>
export default {
	name: 'aio-login-snackbar',

	props: {
		message: {
			type: String,
			required: true,
		},

		duration: {
			type: Number,
			default: 5000,
		},
	},

	data: ( vm ) => ( {
		disable: false,
	} ),

	watch: {
		disable( value ) {
			if ( value ) {
				this.$emit( 'close' );
			}
		},
	},

	methods: {
		handleClose() {
			this.disable = true;
		},
	},

	mounted() {
		setTimeout( () => {
			this.disable = true;
		}, this.duration );
	},
}
</script>

<style scoped>
.aio-login__snackbar {
	position: fixed;
	bottom: 40px;
	right: 40px;
	background: #9516df;
	color: #fff;
	padding: 10px 20px;
	border-radius: 5px;
}

.aio-login__snackbar__content {
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.aio-login__snackbar__content button {
	background: none;
	border: none;
	color: #fff;
	cursor: pointer;
}
</style>