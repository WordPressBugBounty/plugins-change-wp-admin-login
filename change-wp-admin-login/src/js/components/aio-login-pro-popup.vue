<template>

	<div>
		<aio-login-popup width="576px" height="340px" content="nocontent" v-on:close-popup="e => $emit( 'close-popup', e )">
			<template v-slot:default>
				<div class="aio-login-pro__container">
					<div class="text-center aio-login__popup-container">
						<img :src="assets_url + 'images/dashboard-logo-white.png'" alt="AIO Login Logo">

						<h2 class="aio-login__title">To access more features and options</h2>

						<a class="aio-login__button" href="https://aiologin.com/pricing/?utm_source=plugin&utm_medium=pro_pop_up&utm_campaign=plugin">Get AIO Login Pro</a>
					</div>
				</div>
			</template>
		</aio-login-popup>
	</div>

</template>

<script>
export default {
	name: 'aio-login-pro-popup',
	data: () => ( {
		assets_url: window.aio_login__app_object.assets_url,
	} ),
}
</script>

<style scoped>
.aio-login-pro__container {

	display: flex;
	justify-content: center;
	align-items: center;

	width: 576px;
	height: 340px;
	background-color: #490f95;
	box-shadow: 0px 4px 16px 6px rgba( 0, 0, 0, 0.08 );
	position: relative;
	background-image: url( ../../images/popup-left.png ), url( ../../images/popup-right.png );
	background-repeat: no-repeat;
	background-position: left bottom, right bottom;
	overflow: hidden;
}

.aio-login-pro__container::before {
	content: '';
	position: absolute;
	bottom: 0;
	left: -120px;
	width: 270px;
	height: 270px;
	background-color: #0c9;
	opacity: 0.5;
	border-radius: 270px;
	filter: blur( 125px )
}

.aio-login-pro__container::after {
	content: '';
	position: absolute;
	bottom: 0;
	right: -120px;
	width: 270px;
	height: 270px;
	background-color: #FFBA08;
	opacity: 0.5;
	border-radius: 270px;
	filter: blur( 125px )
}

.aio-login__button {
	display: inline-flex;
	padding: 16px 32px;
	justify-content: center;
	align-items: center;
	gap: 10px;

	border-radius: 4px;
	background: #FFBA08;

	text-decoration: none;

	color: #490F95;
	font-family: Figtree;
	font-size: 18px;
	font-style: normal;
	font-weight: 600;
	line-height: 100%; /* 18px */
}

.aio-login__title {
	color: #FFF;
	font-family: Figtree;
	font-size: 24px;
	font-style: normal;
	font-weight: 400;
	line-height: 24px; /* 100% */
}
</style>