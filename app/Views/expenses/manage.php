<?php echo view("partial/header") ?>

<script type="text/javascript">
$(document).ready(function()
{
	// when any filter is clicked and the dropdown window is closed
	$('#filters').on('hidden.bs.select', function(e) {
		table_support.refresh();
	});
	
	// load the preset datarange picker
	<?php echo view('partial/daterangepicker') ?>

	$("#daterangepicker").on('apply.daterangepicker', function(ev, picker) {
		table_support.refresh();
	});

	<?php echo view('partial/bootstrap_tables_locale') ?>

	table_support.init({
		resource: '<?php echo site_url($controller_name) ?>',
		headers: <?php echo $table_headers ?>,
		pageSize: <?php echo $this->appconfig->get('lines_per_page') ?>,
		uniqueId: 'expense_id',
		onLoadSuccess: function(response) {
			if($("#table tbody tr").length > 1) {
				$("#payment_summary").html(response.payment_summary);
				$("#table tbody tr:last td:first").html("");
			}
		},
		queryParams: function() {
			return $.extend(arguments[0], {
				start_date: start_date,
				end_date: end_date,
				filters: $("#filters").val() || [""]
			});
		}
	});
});
</script>

<?php echo view('partial/print_receipt', ['print_after_sale'=>false, 'selected_printer'=>'takings_printer')) ?>

<div id="title_bar" class="print_hide btn-toolbar">
	<button onclick="javascript:printdoc()" class='btn btn-info btn-sm pull-right'>
		<span class="glyphicon glyphicon-print">&nbsp;</span><?php echo lang('Common.print') ?>
	</button>
	<button class='btn btn-info btn-sm pull-right modal-dlg' data-btn-submit='<?php echo lang('Common.submit') ?>' data-href='<?php echo site_url($controller_name."/view") ?>'
			title='<?php echo lang($controller_name . 'new') ?>'>
		<span class="glyphicon glyphicon-tags">&nbsp</span><?php echo lang($controller_name . '.new') ?>
	</button>
</div>

<div id="toolbar">
	<div class="pull-left form-inline" role="toolbar">
		<button id="delete" class="btn btn-default btn-sm print_hide">
			<span class="glyphicon glyphicon-trash">&nbsp</span><?php echo lang('Common.delete') ?>
		</button>

		<?php echo form_input (['name'=>'daterangepicker', 'class'=>'form-control input-sm', 'id'=>'daterangepicker')) ?>
		<?php echo form_multiselect('filters[]', $filters, '', ['id'=>'filters', 'data-none-selected-text'=>lang('Common.none_selected_text'), 'class'=>'selectpicker show-menu-arrow', 'data-selected-text-format'=>'count > 1', 'data-style'=>'btn-default btn-sm', 'data-width'=>'fit')) ?>
	</div>
</div>

<div id="table_holder">
	<table id="table"></table>
</div>

<div id="payment_summary">
</div>

<?php echo view("partial/footer") ?>