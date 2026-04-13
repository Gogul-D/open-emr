<?php
/* Smarty version 4.3.4, created on 2025-02-11 14:29:23
  from '/var/www/openemr/gacl/admin/templates/phpgacl/acl_list.tpl' */

/* @var Smarty_Internal_Template $_smarty_tpl */
if ($_smarty_tpl->_decodeProperties($_smarty_tpl, array (
  'version' => '4.3.4',
  'unifunc' => 'content_67abc1335508d7_57382371',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '880e36b60beff199f49b5d41318d785e287d21f7' => 
    array (
      0 => '/var/www/openemr/gacl/admin/templates/phpgacl/acl_list.tpl',
      1 => 1700108884,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
    'file:phpgacl/header.tpl' => 1,
    'file:phpgacl/acl_admin_js.tpl' => 1,
    'file:phpgacl/navigation.tpl' => 1,
    'file:phpgacl/pager.tpl' => 2,
    'file:phpgacl/footer.tpl' => 1,
  ),
),false)) {
function content_67abc1335508d7_57382371 (Smarty_Internal_Template $_smarty_tpl) {
$_smarty_tpl->_checkPlugins(array(0=>array('file'=>'/var/www/openemr/vendor/smarty/smarty/libs/plugins/function.html_options.php','function'=>'smarty_function_html_options',),1=>array('file'=>'/var/www/openemr/vendor/smarty/smarty/libs/plugins/function.cycle.php','function'=>'smarty_function_cycle',),2=>array('file'=>'/var/www/openemr/vendor/smarty/smarty/libs/plugins/modifier.date_format.php','function'=>'smarty_modifier_date_format',),));
$_smarty_tpl->_subTemplateRender("file:phpgacl/header.tpl", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), 0, false);
$_smarty_tpl->_subTemplateRender("file:phpgacl/acl_admin_js.tpl", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), 0, false);
?>

    <style type="text/css">
	ul {
		padding: 0px 0px 0px 0px;
		margin: 0px 0px 0px 0px;
		list-style-type: none;
	}
	ul li {
		padding: 0px;
		margin: 0px;
		font-weight: bold;
	}
	ol {
		padding: 0px 0px 0px 22px;
		margin: 0px;
	}
	ol li {
		padding: 0px;
		margin: 0px;
		font-weight: normal;
	}
	div.divider {
		margin: 2px 0px;
		padding: 0px;
		border-bottom: 1px solid grey;
	}
	input.filter {
		width: 99%;
	}
	select.filter {
		width: 99%;
		margin-top: 0px;
	}
   </style>

  </head>
<body>
<?php $_smarty_tpl->_subTemplateRender("file:phpgacl/navigation.tpl", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), 0, false);
?>
<form method="get" name="acl_list" action="acl_list.php">
<input type="hidden" name="csrf_token_form" value="<?php echo attr($_smarty_tpl->tpl_vars['CSRF_TOKEN_FORM']->value);?>
">
<table cellpadding="2" cellspacing="2" border="2" width="100%">
  <tr align="center">
    <td colspan="6"><b>Filter</b></td>
  </tr>
  <tr>
    <th width="12%">&nbsp;</th>
    <th width="22%">ACO</th>
    <th width="22%">ARO</th>
    <th width="22%">AXO</th>
    <th width="22%" colspan="2">ACL</th>
  </tr>
  <tr valign="middle" align="center">
    <td align="left"><b>Section:</b> </td>
    <td>
		<select name="filter_aco_section" tabindex="0" class="filter">
			<?php echo smarty_function_html_options(array('options'=>$_smarty_tpl->tpl_vars['options_filter_aco_sections']->value,'selected'=>$_smarty_tpl->tpl_vars['filter_aco_section_escaped']->value),$_smarty_tpl);?>

		</select>
    </td>
    <td>
		<select name="filter_aro_section" tabindex="0" class="filter">
			<?php echo smarty_function_html_options(array('options'=>$_smarty_tpl->tpl_vars['options_filter_aro_sections']->value,'selected'=>$_smarty_tpl->tpl_vars['filter_aro_section_escaped']->value),$_smarty_tpl);?>

		</select>
    </td>
    <td>
		<select name="filter_axo_section" tabindex="0" class="filter">
			<?php echo smarty_function_html_options(array('options'=>$_smarty_tpl->tpl_vars['options_filter_axo_sections']->value,'selected'=>$_smarty_tpl->tpl_vars['filter_axo_section_escaped']->value),$_smarty_tpl);?>

		</select>
    </td>
    <td colspan="2">
		<select name="filter_acl_section" tabindex="0" class="filter">
			<?php echo smarty_function_html_options(array('options'=>$_smarty_tpl->tpl_vars['options_filter_acl_sections']->value,'selected'=>$_smarty_tpl->tpl_vars['filter_acl_section_escaped']->value),$_smarty_tpl);?>

		</select>
    </td>
  </tr>
  <tr valign="middle" align="center">
    <td align="left"><b>Object:</b> </td>
    <td><input type="text" name="filter_aco" size="20" value="<?php echo attr($_smarty_tpl->tpl_vars['filter_aco']->value);?>
" class="filter"></td>
    <td><input type="text" name="filter_aro" size="20" value="<?php echo attr($_smarty_tpl->tpl_vars['filter_aro']->value);?>
" class="filter"></td>
    <td><input type="text" name="filter_axo" size="20" value="<?php echo attr($_smarty_tpl->tpl_vars['filter_axo']->value);?>
" class="filter"></td>
    <td align="left" width="11%"><b>Allow:</b> </td>
    <td align="left" width="11%">
		 <select name="filter_allow" tabindex="0" class="filter">
			<?php echo smarty_function_html_options(array('options'=>$_smarty_tpl->tpl_vars['options_filter_allow']->value,'selected'=>$_smarty_tpl->tpl_vars['filter_allow']->value),$_smarty_tpl);?>

		</select>
    </td>
  </tr>
  <tr valign="middle" align="center">
    <td align="left"><b>Group:</b> </td>
    <td>&nbsp;</td>
    <td><input type="text" name="filter_aro_group" size="20" value="<?php echo attr($_smarty_tpl->tpl_vars['filter_aro_group']->value);?>
" class="filter"></td>
    <td><input type="text" name="filter_axo_group" size="20" value="<?php echo attr($_smarty_tpl->tpl_vars['filter_axo_group']->value);?>
" class="filter"></td>
    <td align="left"><b>Enabled:</b> </td>
    <td align="left">
		<select name="filter_enabled" tabindex="0" class="filter">
			<?php echo smarty_function_html_options(array('options'=>$_smarty_tpl->tpl_vars['options_filter_enabled']->value,'selected'=>$_smarty_tpl->tpl_vars['filter_enabled']->value),$_smarty_tpl);?>

		</select>
    </td>
  </tr>
  <tr valign="middle" align="left">
	<td><b>Return&nbsp;Value:</b> </td>
	<td colspan="5"><input type="text" name="filter_return_value" size="50" value="<?php echo attr($_smarty_tpl->tpl_vars['filter_return_value']->value);?>
" class="filter"></td>
  </tr>
  <tr class="controls" align="center">
    <td colspan="6"><input type="submit" class="button" name="action" value="Filter"></td>
  </tr>
</table>
<br />
<table cellpadding="2" cellspacing="2" border="2" width="100%">
  <tr class="pager">
	<td colspan="8">
		<?php $_smarty_tpl->_subTemplateRender("file:phpgacl/pager.tpl", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array('pager_data'=>$_smarty_tpl->tpl_vars['paging_data']->value,'link'=>"?action=".((string)$_smarty_tpl->tpl_vars['action_escaped']->value)."&filter_aco_section=".((string)$_smarty_tpl->tpl_vars['filter_aco_section_escaped']->value)."&filter_aco=".((string)$_smarty_tpl->tpl_vars['filter_aco_escaped']->value)."&filter_aro_section=".((string)$_smarty_tpl->tpl_vars['filter_aro_section_escaped']->value)."&filter_aro=".((string)$_smarty_tpl->tpl_vars['filter_aro_escaped']->value)."&filter_axo_section=".((string)$_smarty_tpl->tpl_vars['filter_axo_section_escaped']->value)."&filter_axo=".((string)$_smarty_tpl->tpl_vars['filter_axo_escaped']->value)."&filter_aro_group=".((string)$_smarty_tpl->tpl_vars['filter_aro_group_escaped']->value)."&filter_axo_group=".((string)$_smarty_tpl->tpl_vars['filter_axo_group_escaped']->value)."&filter_return_value=".((string)$_smarty_tpl->tpl_vars['filter_return_value_escaped']->value)."&filter_allow=".((string)$_smarty_tpl->tpl_vars['filter_allow_escaped']->value)."&filter_enabled=".((string)$_smarty_tpl->tpl_vars['filter_enabled_escaped']->value)."&"), 0, false);
?>
	</td>
  </tr>
  <tr>
    <th width="2%">ID</th>
    <th width="24%">ACO</th>
    <th width="24%">ARO</th>
    <th width="24%">AXO</th>
    <th width="10%">Access</th>
    <th width="10%">Enabled</th>
    <th width="4%">Functions</th>
    <th width="2%"><input type="checkbox" class="checkbox" name="select_all" onClick="checkAll(this)"/></th>
  </tr>

<?php
$_from = $_smarty_tpl->smarty->ext->_foreach->init($_smarty_tpl, $_smarty_tpl->tpl_vars['acls']->value, 'acl');
$_smarty_tpl->tpl_vars['acl']->do_else = true;
if ($_from !== null) foreach ($_from as $_smarty_tpl->tpl_vars['acl']->value) {
$_smarty_tpl->tpl_vars['acl']->do_else = false;
?>
  <?php echo smarty_function_cycle(array('assign'=>'class','values'=>"odd,even"),$_smarty_tpl);?>

  <tr class="<?php echo $_smarty_tpl->tpl_vars['class']->value;?>
">
    <td valign="middle" rowspan="3" align="center"><?php echo text($_smarty_tpl->tpl_vars['acl']->value['id']);?>
</td>
    <td valign="top" align="left">
	<?php if (count($_smarty_tpl->tpl_vars['acl']->value['aco']) > 0) {?>
		<ul>
		<?php
$_from = $_smarty_tpl->smarty->ext->_foreach->init($_smarty_tpl, $_smarty_tpl->tpl_vars['acl']->value['aco'], 'objects', false, 'section');
$_smarty_tpl->tpl_vars['objects']->do_else = true;
if ($_from !== null) foreach ($_from as $_smarty_tpl->tpl_vars['section']->value => $_smarty_tpl->tpl_vars['objects']->value) {
$_smarty_tpl->tpl_vars['objects']->do_else = false;
?>
			<li><?php echo text($_smarty_tpl->tpl_vars['section']->value);?>
<ol>
			<?php
$_from = $_smarty_tpl->smarty->ext->_foreach->init($_smarty_tpl, $_smarty_tpl->tpl_vars['objects']->value, 'obj');
$_smarty_tpl->tpl_vars['obj']->do_else = true;
if ($_from !== null) foreach ($_from as $_smarty_tpl->tpl_vars['obj']->value) {
$_smarty_tpl->tpl_vars['obj']->do_else = false;
?>
				<li><?php echo text($_smarty_tpl->tpl_vars['obj']->value);?>
</li>
			<?php
}
$_smarty_tpl->smarty->ext->_foreach->restore($_smarty_tpl, 1);?>
			</ol></li>
		<?php
}
$_smarty_tpl->smarty->ext->_foreach->restore($_smarty_tpl, 1);?>
		</ul>
	<?php } else { ?>
		&nbsp;
	<?php }?>
    </td>
    <td valign="top" align="left">
	  <?php if (count($_smarty_tpl->tpl_vars['acl']->value['aro']) > 0) {?>
		<ul>
		  <?php
$_from = $_smarty_tpl->smarty->ext->_foreach->init($_smarty_tpl, $_smarty_tpl->tpl_vars['acl']->value['aro'], 'objects', false, 'section');
$_smarty_tpl->tpl_vars['objects']->do_else = true;
if ($_from !== null) foreach ($_from as $_smarty_tpl->tpl_vars['section']->value => $_smarty_tpl->tpl_vars['objects']->value) {
$_smarty_tpl->tpl_vars['objects']->do_else = false;
?>
			<li><?php echo text($_smarty_tpl->tpl_vars['section']->value);?>
<ol>
			<?php
$_from = $_smarty_tpl->smarty->ext->_foreach->init($_smarty_tpl, $_smarty_tpl->tpl_vars['objects']->value, 'obj');
$_smarty_tpl->tpl_vars['obj']->do_else = true;
if ($_from !== null) foreach ($_from as $_smarty_tpl->tpl_vars['obj']->value) {
$_smarty_tpl->tpl_vars['obj']->do_else = false;
?>
				<li><?php echo text($_smarty_tpl->tpl_vars['obj']->value);?>
</li>
			<?php
}
$_smarty_tpl->smarty->ext->_foreach->restore($_smarty_tpl, 1);?>
			</ol></li>
		  <?php
}
$_smarty_tpl->smarty->ext->_foreach->restore($_smarty_tpl, 1);?>
		</ul>
		<?php if (count($_smarty_tpl->tpl_vars['acl']->value['aro_groups']) > 0) {?>
		<div class="divider"></div>
		<?php }?>
	  <?php }?>
	  <?php if (count($_smarty_tpl->tpl_vars['acl']->value['aro_groups']) > 0) {?>
		<b>Groups</b><ol>
		  <?php
$_from = $_smarty_tpl->smarty->ext->_foreach->init($_smarty_tpl, $_smarty_tpl->tpl_vars['acl']->value['aro_groups'], 'group');
$_smarty_tpl->tpl_vars['group']->do_else = true;
if ($_from !== null) foreach ($_from as $_smarty_tpl->tpl_vars['group']->value) {
$_smarty_tpl->tpl_vars['group']->do_else = false;
?>
			<li><?php echo text($_smarty_tpl->tpl_vars['group']->value);?>
</li>
		  <?php
}
$_smarty_tpl->smarty->ext->_foreach->restore($_smarty_tpl, 1);?>
		</ol>
	  <?php }?>
    </td>
    <td valign="top" align="left">
	  <?php if (count($_smarty_tpl->tpl_vars['acl']->value['axo']) > 0) {?>
		<ul>
		  <?php
$_from = $_smarty_tpl->smarty->ext->_foreach->init($_smarty_tpl, $_smarty_tpl->tpl_vars['acl']->value['axo'], 'objects', false, 'section');
$_smarty_tpl->tpl_vars['objects']->do_else = true;
if ($_from !== null) foreach ($_from as $_smarty_tpl->tpl_vars['section']->value => $_smarty_tpl->tpl_vars['objects']->value) {
$_smarty_tpl->tpl_vars['objects']->do_else = false;
?>
			<li><?php echo text($_smarty_tpl->tpl_vars['section']->value);?>
<ol>
			<?php
$_from = $_smarty_tpl->smarty->ext->_foreach->init($_smarty_tpl, $_smarty_tpl->tpl_vars['objects']->value, 'obj');
$_smarty_tpl->tpl_vars['obj']->do_else = true;
if ($_from !== null) foreach ($_from as $_smarty_tpl->tpl_vars['obj']->value) {
$_smarty_tpl->tpl_vars['obj']->do_else = false;
?>
				<li><?php echo text($_smarty_tpl->tpl_vars['obj']->value);?>
</li>
			<?php
}
$_smarty_tpl->smarty->ext->_foreach->restore($_smarty_tpl, 1);?>
			</ol></li>
		  <?php
}
$_smarty_tpl->smarty->ext->_foreach->restore($_smarty_tpl, 1);?>
		</ul>
		<?php if (count($_smarty_tpl->tpl_vars['acl']->value['axo_groups']) > 0) {?>
		<div class="divider"></div>
		<?php }?>
	  <?php }?>
	  <?php if (count($_smarty_tpl->tpl_vars['acl']->value['axo_groups']) > 0) {?>
		<b>Groups</b><ol>
		  <?php
$_from = $_smarty_tpl->smarty->ext->_foreach->init($_smarty_tpl, $_smarty_tpl->tpl_vars['acl']->value['axo_groups'], 'group');
$_smarty_tpl->tpl_vars['group']->do_else = true;
if ($_from !== null) foreach ($_from as $_smarty_tpl->tpl_vars['group']->value) {
$_smarty_tpl->tpl_vars['group']->do_else = false;
?>
			<li><?php echo text($_smarty_tpl->tpl_vars['group']->value);?>
</li>
		  <?php
}
$_smarty_tpl->smarty->ext->_foreach->restore($_smarty_tpl, 1);?>
		</ol>
	  <?php }?>
    </td>
    <td valign="middle" class="<?php if ($_smarty_tpl->tpl_vars['acl']->value['allow']) {?>green<?php } else { ?>red<?php }?>" align="center">
		<?php if ($_smarty_tpl->tpl_vars['acl']->value['allow']) {?>
			ALLOW
		<?php } else { ?>
			DENY
		<?php }?>
    </td>
    <td valign="middle" class="<?php if ($_smarty_tpl->tpl_vars['acl']->value['enabled']) {?>green<?php } else { ?>red<?php }?>" align="center">
		<?php if ($_smarty_tpl->tpl_vars['acl']->value['enabled']) {?>
			Yes
		<?php } else { ?>
			No
		<?php }?>
    </td>
    <td valign="middle" rowspan="3" align="center">
        [ <a href="acl_admin.php?action=edit&acl_id=<?php echo attr_url($_smarty_tpl->tpl_vars['acl']->value['id']);?>
&csrf_token_form=<?php echo attr_url($_smarty_tpl->tpl_vars['CSRF_TOKEN_FORM']->value);?>
&return_page=<?php echo attr_url($_smarty_tpl->tpl_vars['return_page']->value);?>
">Edit</a> ]
    </td>
    <td valign="middle" rowspan="3" align="center">
        <input type="checkbox" class="checkbox" name="delete_acl[]" value="<?php echo attr($_smarty_tpl->tpl_vars['acl']->value['id']);?>
">
    </td>
  </tr>

  <tr class="<?php echo $_smarty_tpl->tpl_vars['class']->value;?>
">
    <td valign="top" colspan="3" align="left">
        <b>Return Value:</b> <?php echo text($_smarty_tpl->tpl_vars['acl']->value['return_value']);?>

    </td>
    <td valign="middle" colspan="2" align="center">
        <?php echo text($_smarty_tpl->tpl_vars['acl']->value['section_name']);?>

    </td>
  </tr>
  <tr class="<?php echo $_smarty_tpl->tpl_vars['class']->value;?>
">
    <td valign="top" colspan="3" align="left">
        <b>Note:</b> <?php echo text($_smarty_tpl->tpl_vars['acl']->value['note']);?>

    </td>
    <td valign="middle" colspan="2" align="center">
        <?php echo text(smarty_modifier_date_format($_smarty_tpl->tpl_vars['acl']->value['updated_date'],"%d-%b-%Y %H:%M:%S"));?>

    </td>
  </tr>
<?php
}
$_smarty_tpl->smarty->ext->_foreach->restore($_smarty_tpl, 1);?>
  <tr class="pager">
	<td colspan="8">
		<?php $_smarty_tpl->_subTemplateRender("file:phpgacl/pager.tpl", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array('pager_data'=>$_smarty_tpl->tpl_vars['paging_data']->value,'link'=>"?action=".((string)$_smarty_tpl->tpl_vars['action_escaped']->value)."&filter_aco_section=".((string)$_smarty_tpl->tpl_vars['filter_aco_section_escaped']->value)."&filter_aco=".((string)$_smarty_tpl->tpl_vars['filter_aco_escaped']->value)."&filter_aro_section=".((string)$_smarty_tpl->tpl_vars['filter_aro_section_escaped']->value)."&filter_aro=".((string)$_smarty_tpl->tpl_vars['filter_aro_escaped']->value)."&filter_axo_section=".((string)$_smarty_tpl->tpl_vars['filter_axo_section_escaped']->value)."&filter_axo=".((string)$_smarty_tpl->tpl_vars['filter_axo_escaped']->value)."&filter_aro_group=".((string)$_smarty_tpl->tpl_vars['filter_aro_group_escaped']->value)."&filter_axo_group=".((string)$_smarty_tpl->tpl_vars['filter_axo_group_escaped']->value)."&filter_return_value=".((string)$_smarty_tpl->tpl_vars['filter_return_value_escaped']->value)."&filter_allow=".((string)$_smarty_tpl->tpl_vars['filter_allow_escaped']->value)."&filter_enabled=".((string)$_smarty_tpl->tpl_vars['filter_enabled_escaped']->value)."&"), 0, true);
?>
	</td>
  </tr>
  <tr class="controls">
    <td colspan="6">&nbsp;</td>
    <td colspan="2" align="center">
      <input type="submit" class="button" name="action" value="Delete">
    </td>
  </tr>
</table>
<input type="hidden" name="return_page" value="<?php echo attr($_smarty_tpl->tpl_vars['return_page']->value);?>
">
</form>
<?php $_smarty_tpl->_subTemplateRender("file:phpgacl/footer.tpl", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), 0, false);
}
}
