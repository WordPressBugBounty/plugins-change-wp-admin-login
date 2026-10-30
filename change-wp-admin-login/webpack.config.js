let path = require( 'path' ),
    webpack               = require( 'webpack' ),
    { VueLoaderPlugin }   = require( 'vue-loader' ),
    MiniCssExtractPlugin  = require( 'mini-css-extract-plugin' );

const sharedRules = [
	{
		test: /\.js$/,
		use: [
			'babel-loader',
		],
	},
	{
		test: /\.vue$/,
		use: [
			'vue-loader',
		],
	},
	{
		test: /\.css$/,
		use: [
			MiniCssExtractPlugin.loader,
			{
				loader: 'css-loader',
				options: {
					// resolve-url-loader needs loader source maps; final .map files stay off via devtool:false.
					sourceMap: true,
				},
			},
			{
				loader: 'resolve-url-loader',
				options: {
					sourceMap: true,
				},
			},
		],
	},
	{
		test: /\.(woff|woff2|eot|ttf|otf)$/i,
		type: 'asset/resource',
		generator: {
			filename: 'fonts/[name][ext][query]',
		},
	},
	{
		test: /\.(png|svg|jpg|jpeg|gif)$/i,
		type: 'asset/resource',
		generator: {
			filename: 'images/[name][ext][query]',
		},
	},
];

const sharedResolve = {
	alias: {
		vue: 'vue/dist/vue.esm-bundler.js',
	},
};

const sharedDefines = new webpack.DefinePlugin( {
	__VUE_OPTIONS_API__: 'true',
	__VUE_PROD_DEVTOOLS__: 'false',
	__VUE_PROD_HYDRATION_MISMATCH_DETAILS__: 'false',
} );

module.exports = [
	// Shared Vue runtime — loaded before Pro + Free app so both use one instance.
	{
		mode: 'production',
		entry: './src/js/vue-vendor.js',
		devtool: 'source-map',
		output: {
			path: path.resolve( __dirname, 'assets' ),
			filename: 'js/vue-vendor.js',
		},
		plugins: [ sharedDefines ],
		module: {
			rules: [
				{
					test: /\.js$/,
					use: [ 'babel-loader' ],
				},
			],
		},
		resolve: sharedResolve,
	},
	// Main Free admin app (Vue / vue-router come from aio-login-vue).
	{
		mode: 'production',
		entry: [
			'./src/js/app.js',
			'./src/css/app.css',
		],
		devtool: 'source-map',
		output: {
			path: path.resolve( __dirname, 'assets' ),
			filename: 'js/app.js',
		},
		plugins: [
			sharedDefines,
			new VueLoaderPlugin(),
			new MiniCssExtractPlugin( {
				filename: 'css/app.css',
			} ),
		],
		module: {
			rules: sharedRules,
		},
		resolve: sharedResolve,
		externals: {
			jquery: 'jQuery',
			vue: 'vue',
			'vue-router': 'vueRouter',
		},
	},
];
