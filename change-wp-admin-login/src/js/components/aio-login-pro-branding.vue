<template>
	<div class="aio-login__pro-branding-container">
		<div class="aio-login__pro-flex">
			<div class="aio-login__pro-copy">
				<h2>{{ $t('Get the Most Powerful WordPress Login Plugin Today') }}</h2>
				<p>
					{{ $t('Join over 60K smart website owners who use AIO Login to improve their') }}
					{{ $t('WordPress admin login security and customization') }}
				</p>
			</div>
			<a
				class="aio-login__pro-cta"
				:href="getProPricingUrl"
				target="_blank"
				rel="noopener noreferrer"
			>{{ $t('Get AIO Login Pro') }}</a>
		</div>
	</div>
</template>

<script>
export default {
	name: 'aio-login-pro-branding',
	computed: {
		getProPricingUrl() {
			return 'https://aiologin.com/pricing/?utm_source=plugin&utm_medium=dashboard_pro_banner&utm_campaign=plugin';
		},
	},
}
</script>

<style scoped>
/* Figma 766:3794 — Dashboard Banner (1200×233). Background = exported Shape layer. */
.aio-login__pro-branding-container {
	position: relative;
	isolation: isolate;
	overflow: hidden;
	box-sizing: border-box;
	width: 100%;
	min-height: 233px;
	border-radius: 17px;
	padding: 42px 67px;
	background-color: #9516DF;
	background-image: url("../../images/dashboard-banner/banner-bg.png");
	background-repeat: no-repeat;
	background-position: center center;
	background-size: cover;
}

.aio-login__pro-flex {
	position: relative;
	z-index: 1;
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 40px;
	width: 100%;
	min-height: 146px;
}

.aio-login__pro-copy {
	max-width: 552px;
	flex: 1 1 auto;
}

.aio-login__pro-branding-container h2 {
	margin: 0 0 17px !important;
	padding: 0 !important;
	color: #fff !important;
	font-family: Figtree, sans-serif !important;
	font-weight: 700 !important;
	font-size: 35px !important;
	line-height: 1.3 !important;
}

.aio-login__pro-branding-container p {
	margin: 0 !important;
	padding: 0 !important;
	max-width: 552px;
	color: #EBC8FF !important;
	font-family: Figtree, sans-serif !important;
	font-weight: 500 !important;
	font-size: 17px !important;
	line-height: 1.5 !important;
}

.aio-login__pro-cta {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	flex-shrink: 0;
	box-sizing: border-box;
	min-width: 267px;
	height: 54px;
	padding: 0 29px;
	border-radius: 8px;
	background: #FBBC09;
	color: #3D035F !important;
	font-family: Figtree, sans-serif !important;
	font-weight: 700 !important;
	font-size: 18px !important;
	line-height: 1.35 !important;
	text-decoration: none !important;
	white-space: nowrap;
	transition: background 0.15s ease;
}

.aio-login__pro-cta:hover,
.aio-login__pro-cta:focus {
	background: #f0b008;
	color: #3D035F !important;
}

@media screen and (max-width: 1100px) {
	.aio-login__pro-branding-container {
		padding: 36px 40px;
	}

	.aio-login__pro-branding-container h2 {
		font-size: 28px !important;
	}
}

@media screen and (max-width: 960px) {
	.aio-login__pro-branding-container {
		min-height: 0;
		padding: 28px 24px;
	}

	.aio-login__pro-flex {
		flex-direction: column;
		align-items: flex-start;
		gap: 22px;
		min-height: 0;
	}

	.aio-login__pro-branding-container h2 {
		font-size: 22px !important;
		margin-bottom: 12px !important;
	}

	.aio-login__pro-branding-container p {
		font-size: 15px !important;
	}

	.aio-login__pro-branding-container p br {
		display: none;
	}

	.aio-login__pro-cta {
		width: 100%;
		min-width: 0;
	}
}
</style>
