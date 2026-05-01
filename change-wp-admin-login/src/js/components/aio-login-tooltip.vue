<template>
	<span class="aio-login-tooltip-wrap" ref="wrapRef" @mouseenter="show" @mouseleave="hide">
		<span
			class="aio-login-tooltip-icon"
			aria-label="Help"
			role="img"
		>?</span>
		<transition name="aio-login-tooltip-fade">
			<div
				v-show="visible"
				class="aio-login-tooltip-modal"
				:class="placement"
				ref="popoverRef"
				@mouseenter="keepVisible"
				@mouseleave="hide"
				role="tooltip"
			>
				<p v-if="title" class="aio-login-tooltip-title">{{ title }}</p>
				<div class="aio-login-tooltip-body" v-html="content"></div>
			</div>
		</transition>
	</span>
</template>

<script>
export default {
	name: 'aio-login-tooltip',
	props: {
		content: {
			type: String,
			default: '',
		},
		title: {
			type: String,
			default: '',
		},
		placement: {
			type: String,
			default: 'bottom',
			validator: (v) => ['top', 'bottom', 'left', 'right'].includes(v),
		},
	},
	data() {
		return {
			visible: false,
			hideTimer: null,
		};
	},
	methods: {
		show() {
			if (this.hideTimer) {
				clearTimeout(this.hideTimer);
				this.hideTimer = null;
			}
			this.visible = true;
		},
		keepVisible() {
			if (this.hideTimer) {
				clearTimeout(this.hideTimer);
				this.hideTimer = null;
			}
		},
		hide() {
			this.hideTimer = setTimeout(() => {
				this.visible = false;
				this.hideTimer = null;
			}, 100);
		},
	},
};
</script>

<style scoped>
.aio-login-tooltip-wrap {
	display: inline-flex;
	align-items: center;
	vertical-align: middle;
	margin-left: 6px;
	position: relative;
}
.aio-login-tooltip-icon {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 18px;
	height: 18px;
	border-radius: 50%;
	border: 1px solid #c9d2e3;
	background: #f5f6f9;
	color: #7691b2;
	font-size: 12px;
	font-weight: 600;
	line-height: 1;
}
.aio-login-tooltip-icon:hover {
	border-color: #9516df;
	color: #9516df;
	background: #faf5fd;
}
.aio-login-tooltip-modal {
	position: absolute;
	z-index: 100000;
	min-width: 220px;
	max-width: 360px;
	padding: 12px 14px;
	background: #fff;
	border: 1px solid #e8e8e8;
	border-radius: 8px;
	box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
	font-size: 13px;
	line-height: 1.5;
	color: #333;
	text-align: left;
	pointer-events: auto;
}
.aio-login-tooltip-modal.bottom {
	top: calc(100% + 8px);
	left: 0;
}
.aio-login-tooltip-modal.top {
	bottom: calc(100% + 8px);
	left: 0;
}
.aio-login-tooltip-modal.left {
	right: calc(100% + 8px);
	top: 50%;
	transform: translateY(-50%);
}
.aio-login-tooltip-modal.right {
	left: calc(100% + 8px);
	top: 50%;
	transform: translateY(-50%);
}
.aio-login-tooltip-title {
	margin: 0 0 6px 0;
	font-weight: 600;
	color: #151515;
}
.aio-login-tooltip-body {
	margin: 0;
}
.aio-login-tooltip-body :deep(p) {
	margin: 0 0 6px 0;
}
.aio-login-tooltip-body :deep(p:last-child) {
	margin-bottom: 0;
}
.aio-login-tooltip-fade-enter-active,
.aio-login-tooltip-fade-leave-active {
	transition: opacity 0.15s ease;
}
.aio-login-tooltip-fade-enter-from,
.aio-login-tooltip-fade-leave-to {
	opacity: 0;
}
</style>
