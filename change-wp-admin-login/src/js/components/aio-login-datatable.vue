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
			if ( this.datatable ) {
				this.datatable.destroy();
			}
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
			const timeColumnIndex = this.headers.findIndex(
				header => header && header.key === 'time'
			);

			var kf = {
				columns: this.getColumns(),
				data: this.rows,
				responsive: true,
			}
			if ( timeColumnIndex >= 0 ) {
				kf.order = [ [ timeColumnIndex, 'desc' ] ];
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

