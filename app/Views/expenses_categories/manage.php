<?php echo view("partial/header") ?>

<script type="text/javascript">
$(document).ready(function()
{
	<?php echo view('partial/bootstrap_tables_locale') ?>

	table_support.init({
		resource: '<?php echo site_url($controller_name) ?>',
		headers: <?php echo $table_headers ?>,
		pageSize: <?php echo $this->appconfig->get('lines_per_page') ?>,
		uniqueId: 'expense_category_id',
		
	});

	// when any filter is clicked and the dropdown window is closed
	$('#filters').on('hidden.bs.select', function(e)
	{
		table_support.refresh();
	});
});
</script>

<div id="title_bar" class="btn-toolbar">
	<button class='btn btn-info btn-sm pull-right modal-dlg' data-btn-submit='<?php echo lang('Common.submit') ?>' data-href='<?php echo site_url($controller_name."/view") ?>'
			title='<?php echo lang($controller_name . '.new') ?>'>
		<span class="glyphicon glyphicon-list">&nbsp</span><?php echo lang($controller_name . '.new') ?>
	</button>
</div>

<div id="toolbar">
	<div class="pull-left form-inline" role="toolbar">
		<button id="delete" class="btn btn-default btn-sm print_hide">
			<span class="glyphicon glyphicon-trash">&nbsp</span><?php echo lang('Common.delete') ?>
		</button>
	</div>
</div>

<div id="table_holder">
	<table id="table"></table>
</div>

<?php echo view("partial/footer") ?>