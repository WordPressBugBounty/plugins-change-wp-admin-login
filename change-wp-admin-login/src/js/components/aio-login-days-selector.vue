<template>
	<div :id="'aio-login__days-selector-' + type" :class="container_class">
		<p style="margin-top: 0;" @click="toggleDaysSelector">
			<span>{{ title }}</span>
			<span class="aio-login__selector"></span>
		</p>

		<div
			:id="'days-selector-' + type + '-container'"
			class="aio-login__days-selector container"
			:style="( days_selector ) ? 'display: block;' : 'display: none;'"
		>
			<div>
				<label :for="'today-' + type">
					<input
						type="radio"
						:id="'today-' + type"
						:name="'days-' + type"
						value="today"
						@click="updateDays"
						checked
					>
					{{ $t('Today') }}
				</label>
			</div>
			<div>
				<label :for="'last-7-days-' + type">
					<input
						type="radio"
						:id="'last-7-days-' + type"
						:name="'days-' + type"
						value="last_7_days"
						@click="updateDays"
					>
					{{ $t('Last 7 days') }}
				</label>
			</div>
			<div>
				<label :for="'last-14-days-' + type">
					<input
						type="radio"
						:id="'last-14-days-' + type"
						:name="'days-' + type"
						value="last_14_days"
						@click="updateDays"
					>
					{{ $t('Last 14 days') }}
				</label>
			</div>
			<div>
				<label :for="'last-month-days-' + type">
					<input
						type="radio"
						:id="'last-month-days-' + type"
						:name="'days-' + type"
						value="last_month"
						@click="updateDays"
					>
					{{ $t('Last month') }}
				</label>
			</div>
		</div>
	</div>
</template>

<script>
import { t } from '../i18n.js';

export default {
	name: 'aio-login-days-selector',

	props: {
		type: {
			type: String,
			required: true,
		},
	},

	data: () => ( {
		title: t( 'Today' ),
		days_selector: false,
		days: {
			'last_month': t( 'Last month' ),
			'last_14_days': t( 'Last 14 days' ),
			'last_7_days': t( 'Last 7 days' ),
			'today': t( 'Today' ),
		},
	} ),

	watch: {
		days_selector( value ) {
			if ( value ) {
				document.getElementById( 'days-selector-' + this.type + '-container' ).style.display = 'block';
			} else {
				document.getElementById( 'days-selector-' + this.type + '-container' ).style.display = 'none';
			}
		}
	},

	computed: {
		container_class() {
			return {
				'aio-login__card-selector-container': true,
				'container-success': 'success' === this.type,
				'container-failed': 'failed' === this.type,
				'container-lockouts': 'lockouts' === this.type,
			};
		},
	},

	methods: {
		toggleDaysSelector() {
			this.days_selector = !this.days_selector;
		},

		updateDays( e ) {
			this.title = this.days[ e.target.value ];
			this.$emit( 'update-days', e.target.value, this.type )
			this.days_selector = false;
		},
	},

	mounted() {
		document.addEventListener( 'click', ( event ) => {
			if ( ! event.target.closest( '#aio-login__days-selector-' + this.type ) ) {
				this.days_selector = false;
			}
		} );
	}
}
</script>

<style scoped>
.aio-login__selector {
	display: inline-block;
	width: 7px;
	height: 7px;
	border-top-width: 1.5px;
	border-top-style: solid;
	border-left-width: 1.5px;
	border-left-style: solid;
	transform: rotate( 225deg );
	flex-shrink: 0;
}

.aio-login__card-selector-container {
	position: relative;
}

.aio-login__card-selector-container p {
	margin: 0;
	font-size: 18px;
	font-weight: 500;
	line-height: 1.2;
	cursor: pointer;
	display: inline-flex;
	align-items: center;
	gap: 4px;
}

.container-success .aio-login__selector {
	border-color: #6B998F;
}

.container-failed .aio-login__selector {
	border-color: #99876B;
}

.container-lockouts .aio-login__selector {
	border-color: #796B99;
}

.aio-login__days-selector {
	position: absolute;
	background: #fff;
	border: 1px solid #ebebeb;
	box-shadow: 0 4px 16px 0 rgba(0, 0, 0, 0.08);
	top: calc(100% + 8px);
	left: 0;
	border-radius: 8px;
	padding: 10px 12px !important;
	z-index: 30;
	min-width: 168px;
	box-sizing: border-box;
}

.aio-login__days-selector > div + div {
	margin-top: 4px;
}

.aio-login__days-selector label {
	display: flex;
	align-items: center;
	gap: 10px;
	margin: 0;
	padding: 6px 4px;
	font-size: 14px;
	font-weight: 500;
	color: #405980;
	line-height: 1.3;
	cursor: pointer;
	border-radius: 4px;
}

.aio-login__days-selector label:hover {
	background: #f6f7f7;
}

/* Override WP admin radio chrome — compact brand radios. */
.aio-login__days-selector input[type="radio"] {
	-webkit-appearance: none;
	appearance: none;
	width: 16px !important;
	height: 16px !important;
	min-width: 16px;
	margin: 0 !important;
	padding: 0 !important;
	border: 1.5px solid #9516DF !important;
	border-radius: 50% !important;
	background: #fff !important;
	box-shadow: none !important;
	flex-shrink: 0;
	cursor: pointer;
	position: relative;
}

.aio-login__days-selector input[type="radio"]::before {
	content: "" !important;
	display: block !important;
	width: 8px !important;
	height: 8px !important;
	margin: 0 !important;
	border-radius: 50%;
	background: transparent !important;
	box-shadow: none !important;
	position: absolute;
	top: 50%;
	left: 50%;
	transform: translate(-50%, -50%) scale(0);
	transition: transform 0.12s ease-in-out;
}

.aio-login__days-selector input[type="radio"]:checked {
	border-color: #9516DF !important;
	background: #fff !important;
}

.aio-login__days-selector input[type="radio"]:checked::before {
	background: #9516DF !important;
	transform: translate(-50%, -50%) scale(1);
}

.aio-login__days-selector input[type="radio"]:focus {
	outline: none;
	box-shadow: 0 0 0 1px #fff, 0 0 0 3px rgba(149, 22, 223, 0.25) !important;
}

</style>