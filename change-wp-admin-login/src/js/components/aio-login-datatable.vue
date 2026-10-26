<template>
	<div class="aio-login-datatable-wrap">
		<table ref="table">
			<thead>
				<tr>
					<th
						v-for="header in headers"
						:key="header.key"
					>
						{{ header.value }}
					</th>
				</tr>
			</thead>
			<tfoot>
				<tr>
					<th
						v-for="header in headers"
						:key="header.key"
					>
						{{ header.value }}
					</th>
				</tr>
			</tfoot>
		</table>
	</div>
</template>

<script>
import { t } from '../i18n.js';

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
		rebuildTimer: null,
	} ),

	watch: {
		rows: {
			handler() {
				this.scheduleRebuild();
			},
			deep: true,
		},
		headers: {
			handler() {
				this.scheduleRebuild();
			},
			deep: true,
		},
	},

	methods: {
		getColumns() {
			return this.headers.map( ( header ) => {
				if ( header.callback ) {
					return {
						title: header.value,
						data: header.key,
						defaultContent: '',
						render: header.callback,
					};
				}
				return {
					title: header.value,
					data: header.key,
					defaultContent: '',
				};
			} );
		},

		getTableEl() {
			return this.$refs.table || null;
		},

		destroyDatatable() {
			if ( this.datatable ) {
				try {
					this.datatable.destroy();
				} catch ( e ) {
					// Ignore — table may already be torn down by Vue.
				}
				this.datatable = null;
			}

			const el = this.getTableEl();
			if ( ! el ) {
				return;
			}

			// Clear any leftover DataTables class/state if destroy left it registered.
			try {
				if ( window.jQuery && window.jQuery.fn && window.jQuery.fn.dataTable ) {
					const $el = window.jQuery( el );
					if ( window.jQuery.fn.dataTable.isDataTable( $el ) ) {
						$el.DataTable().destroy();
					}
				}
			} catch ( e ) {
				// Ignore.
			}
		},

		getLanguage() {
			return {
				emptyTable: t( 'No data available in table' ),
				info: t( 'Showing _START_ to _END_ of _TOTAL_ entries' ),
				infoEmpty: t( 'Showing 0 to 0 of 0 entries' ),
				infoFiltered: t( '(filtered from _MAX_ total entries)' ),
				lengthMenu: t( '_MENU_ entries per page' ),
				loadingRecords: t( 'Loading...' ),
				processing: t( 'Processing...' ),
				search: t( 'Search:' ),
				zeroRecords: t( 'No matching records found' ),
				paginate: {
					first: t( 'First' ),
					last: t( 'Last' ),
					next: t( 'Next' ),
					previous: t( 'Previous' ),
				},
			};
		},

		createDatatableInstance() {
			const el = this.getTableEl();
			if ( ! el ) {
				return null;
			}

			const timeColumnIndex = this.headers.findIndex(
				( header ) => header && header.key === 'time'
			);

			const options = {
				columns: this.getColumns(),
				data: Array.isArray( this.rows ) ? this.rows : [],
				responsive: true,
				destroy: true,
				language: this.getLanguage(),
			};

			if ( timeColumnIndex >= 0 ) {
				options.order = [ [ timeColumnIndex, 'desc' ] ];
			}

			return new Datatable.default( el, options );
		},

		scheduleRebuild() {
			if ( this.rebuildTimer ) {
				clearTimeout( this.rebuildTimer );
			}
			this.rebuildTimer = setTimeout( () => {
				this.rebuildTimer = null;
				this.$nextTick( () => {
					this.destroyDatatable();
					this.$nextTick( () => {
						this.datatable = this.createDatatableInstance();
					} );
				} );
			}, 0 );
		},
	},

	mounted() {
		this.datatable = this.createDatatableInstance();
	},

	beforeUnmount() {
		if ( this.rebuildTimer ) {
			clearTimeout( this.rebuildTimer );
			this.rebuildTimer = null;
		}
		this.destroyDatatable();
	},
};
</script>

<style scoped>
.aio-login-datatable-wrap {
	width: 100%;
}
</style>
