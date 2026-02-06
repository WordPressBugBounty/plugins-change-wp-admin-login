<template>
	<div class="aio-login__get-pro-content">
		<h1 class="text-center">All-In-One Login <span>PRO</span> Features 👑</h1>

    <div>
      <div class="aio-login__features" v-for="feature in features">
        <div class="aio-login__feature" v-for="feature_row in feature">
          <img :src="assetsUrl + 'images/features/' + feature_row.img" :alt="feature_row.img">
          <h3 class="aio-login__feature-title">{{ feature_row.title }}</h3>
          <p class="aio-login__feature-desc">{{ feature_row.description }}</p>
        </div>
      </div>
    </div>

		<div class="text-center mb-25 mt-25">
			<a
				class="aio-login__get-pro-btn text-center"
				href="https://aiologin.com/pricing/?utm_source=plugin&utm_medium=get_pro_tab&utm_campaign=plugin"
			>Get AIO Login Pro</a>
		</div>
	</div>
</template>

<script>
export default {
	name: 'aio-login-get-pro',

	setup: () => ( {
		features: [
      [
        {
          img: 'ban-user-ip.svg',
          title: 'Ban User / IP',
          description: 'Instantly ban WordPress user IP addresses to prevent unauthorized access',
        },
        {
          img: '2fa.svg',
          title: 'App Based 2FA',
          description: 'Enable two-factor authentication via mobile apps for added security',
        },
        {
          img: 'temp-access-link.svg',
          title: 'Temp Access URL',
          description: 'Generate WordPress temp access URL for specific users.',
        },
        {
          img: 'social-login.svg',
          title: 'Social Login',
          description: 'Easily connect your site using popular social accounts like Facebook, Microsoft, Google, and more.',
        },
      ],
      [
        {
          img: 'customize-pro.svg',
          title: 'Customize Design Pro',
          description: 'Take login page customization further with pre-built templates.',
        },
        {
          img: 'whitelist-ip.svg',
          title: 'Whitelist IP Addresses',
          description: 'Allow access to only whitelisted IP addresses to your WordPress login page.',
        },
        {
          img: 'block-login-attempts.svg',
          title: 'Block Login Attempts',
          description: 'You can block any user or IP address from accessing your wp-login page.',
        },
      ],
		],
	} ),

	data: () => ( {
		assetsUrl: aio_login__app_object.assets_url,
	} ),
}
</script>

<style scoped>

.aio-login__get-pro-content h1 {
  color: #FFF;
  font-family: Figtree;
  font-size: 35.236px;
  font-style: normal;
  font-weight: 700;
  line-height: 62.642px; /* 177.778% */
}

.aio-login__get-pro-content h1 span {
  color: #FBBC09;
}

.text-center {
	text-align: center;
}
.aio-login__get-pro-btn {
	text-decoration: none;

	margin-top: 20px;
	font-weight: 600;
	font-size: 18px;
	border-radius: 4px;

  animation: spin 2.5s infinite linear paused;
  background: linear-gradient(to bottom, #000, #000) padding-box, conic-gradient(from var(--bg-angle) in oklch longer hue, #6013C3 0 0, #E56767 0 0, #00CC99 0 0, #FFC130 0 0) border-box !important;
  border: 2px solid transparent;
  animation-play-state: running;
  color: #fff;
  padding: 16px 49px;
  display: inline-flex;
  align-items: center;
  gap: 10px;
  line-height: 27px;
}
.aio-login__get-pro-btn::after {
  content: "";
  background: url(../../images/aoa-arrow.svg) no-repeat;
  width: 40px;
  height: 15px;
}
@property --bg-angle {
  inherits: false;
  initial-value: 0deg;
  syntax: "<angle>";
}
@keyframes spin {
  to {
    --bg-angle: 360deg;
  }
}

.aio-login__feature-title {
  color: #FFF;
  leading-trim: both;
  text-edge: cap;
  font-family: Figtree;
  font-size: 26px;
  font-style: normal;
  font-weight: 700;
  line-height: 62.642px; /* 240.932% */
  margin-top: 0;
  margin-bottom: 22px;
}

.aio-login__feature-desc {
  color: #EBC8FF;
  leading-trim: both;
  text-edge: cap;
  font-family: Figtree;
  font-size: 16px;
  font-style: normal;
  font-weight: 500;
  line-height: normal;
  margin-top: 0;
}

.aio-login__feature {
  width: 305px;
  margin-right: 80px;
}

.aio-login__feature:last-child {
  margin-right: 0;
}

.aio-login__features {
  display: flex;
  flex-direction: row;
  justify-content: center;
  margin-bottom: 20px;
}

.aio-login__features:last-child {
  margin-bottom: 0;
}

</style>