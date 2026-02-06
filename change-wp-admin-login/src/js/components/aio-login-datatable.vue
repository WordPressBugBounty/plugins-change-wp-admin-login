<template>
<table>
	<thead>
	<tr>
		<th v-for="header in headers">{{ header['value'] }}</th>
	</tr>
	</thead>

	<tfoot>
	<tr>
		<th v-for="header in headers">{{ header['value'] }}</th>
	</tr>
	</tfoot>
</table>
</template>

<script>
export default {
	name: 'aio-login-datatable',

	props: {
		headers: {
			type: Array,
			required: true,
		},

		rows: {
			type: Array,
			default: () => [],
		},
	},

	data: () => ( {
		datatable: null,
	} ),

	watch: {
		rows() {
			this.datatable.destroy();
			this.datatable = this.createDatatableInstance();
		}
	},

	methods: {
		getColumns() {
			return this.headers.map( header => {
				if ( header.callback ) {
					return {
						title: header['value'],
						data: header['key'],
						render: header.callback,
					};
				}
				return {
					title: header['value'],
					data: header['key'],
				};
			} );
		},

		createDatatableInstance() {

			var kf = {
				columns: this.getColumns(),
				data: this.rows,
				responsive: true,
			}
			return new Datatable.default(
				this.$el,
				kf
			);
		}
	},

	mounted() {
		this.datatable = this.createDatatableInstance();
	},
}
</script>

<style scoped>
</style>

