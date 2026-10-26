<template>
	<div class="aio-login__get-pro-shell">
		<div class="aio-login__get-pro-banner">
			<img
				class="aio-login__get-pro-banner-img"
				:src="asset('banner.png')"
				alt=""
				width="1200"
				height="500"
				decoding="async"
			>

			<!-- Invisible hit-area over Figma CTA so Upgrade stays clickable -->
			<a
				class="aio-login__get-pro-banner-cta"
				:href="pricingUrl"
				target="_blank"
				rel="noopener noreferrer"
				:aria-label="$t('Upgrade to PRO')"
			></a>
		</div>
	</div>
</template>

<script>
export default {
	name: 'aio-login-get-pro',

	data: () => ( {
		assetsUrl: aio_login__app_object.assets_url,
		pricingUrl: ( aio_login__app_object && aio_login__app_object.get_pro_pricing_url )
			? aio_login__app_object.get_pro_pricing_url
			: 'https://aiologin.com/?utm_source=plugin&utm_medium=wp_plugin_get_pro&utm_campaign=upgrade_pro&utm_id=upgrade_pro',
	} ),

	methods: {
		asset( file ) {
			return this.assetsUrl + 'images/get-pro/' + file + '?v=12';
		},
	},
};
</script>

<style scoped>
.aio-login__get-pro-shell {
	background: #fff;
	padding: 20px;
	box-sizing: border-box;
}

/* Figma 886:684 — full content width */
.aio-login__get-pro-banner {
	position: relative;
	overflow: hidden;
	border-radius: 10px;
	width: 100%;
	aspect-ratio: 1200 / 500;
	background: #141b34;
	line-height: 0;
}

.aio-login__get-pro-banner-img {
	display: block;
	width: 100%;
	height: 100%;
	object-fit: cover;
	object-position: left center;
	border-radius: 10px;
	pointer-events: none;
	user-select: none;
}

/* Figma CTA 891:707 — measured on 1200×500 export */
.aio-login__get-pro-banner-cta {
	position: absolute;
	left: 4.33%;
	top: 73.4%;
	width: 19.17%;
	height: 10.6%;
	min-width: 140px;
	min-height: 40px;
	border-radius: 10px;
	z-index: 2;
	cursor: pointer;
	text-decoration: none;
	background: transparent;
}

.aio-login__get-pro-banner-cta:focus-visible {
	outline: 2px solid #ffc107;
	outline-offset: 3px;
}

@media (max-width: 720px) {
	.aio-login__get-pro-shell {
		padding: 12px;
	}

	.aio-login__get-pro-banner-cta {
		left: 4%;
		top: 72%;
		width: 42%;
		height: 11%;
		min-width: 0;
	}
}
</style>
