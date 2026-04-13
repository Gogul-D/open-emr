<?php
/* Smarty version 4.3.4, created on 2025-04-21 11:38:37
  from '/var/www/openemr_tb/gacl/admin/templates/phpgacl/acl_debug.tpl' */

/* @var Smarty_Internal_Template $_smarty_tpl */
if ($_smarty_tpl->_decodeProperties($_smarty_tpl, array (
  'version' => '4.3.4',
  'unifunc' => 'content_6806829d1d9e61_83678781',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'b15a92785e1e56907d59905fa5138561940d2d2c' => 
    array (
      0 => '/var/www/openemr_tb/gacl/admin/templates/phpgacl/acl_debug.tpl',
      1 => 1700108884,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
    'file:phpgacl/header.tpl' => 1,
    'file:phpgacl/navigation.tpl' => 1,
    'file:phpgacl/footer.tpl' => 1,
  ),
),false)) {
function content_6806829d1d9e61_83678781 (Smarty_Internal_Template $_smarty_tpl) {
$_smarty_tpl->_subTemplateRender("file:phpgacl/header.tpl", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), 0, false);
?>
  </head>
<body>
<?php $_smarty_tpl->_subTemplateRender("file:phpgacl/navigation.tpl", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), 0, false);
?>
<form method="get" name="acl_debug" action="acl_debug.php">
<table cellpadding="2" cellspacing="2" border="2" width="100%">
  <tr>
  	<th rowspan="2">&nbsp;</th>
  	<th colspan="2">ACO</th>
  	<th colspan="2">ARO</th>
  	<th colspan="2">AXO</th>
    <th rowspan="2">Root ARO<br />Group ID</th>
    <th rowspan="2">Root AXO<br />Group ID</th>
    <th rowspan="2">&nbsp;</th>
  </tr>
  <tr>
    <th>Section</th>
    <th>Value</th>
    <th>Section</th>
    <th>Value</th>
    <th>Section</th>
    <th>Value</th>
  </tr>
  <tr valign="middle" align="center">
    <td nowrap><b>acl_query(</b></td>
    <td><input type="text" name="aco_section_value" size="15" value="<?php echo attr($_smarty_tpl->tpl_vars['aco_section_value']->value);?>
"></td>
    <td><input type="text" name="aco_value" size="15" value="<?php echo attr($_smarty_tpl->tpl_vars['aco_value']->value);?>
"></td>
    <td><input type="text" name="aro_section_value" size="15" value="<?php echo attr($_smarty_tpl->tpl_vars['aro_section_value']->value);?>
"></td>
    <td><input type="text" name="aro_value" size="15" value="<?php echo attr($_smarty_tpl->tpl_vars['aro_value']->value);?>
"></td>
    <td><input type="text" name="axo_section_value" size="15" value="<?php echo attr($_smarty_tpl->tpl_vars['axo_section_value']->value);?>
"></td>
    <td><input type="text" name="axo_value" size="15" value="<?php echo attr($_smarty_tpl->tpl_vars['axo_value']->value);?>
"></td>
    <td><input type="text" name="root_aro_group_id" size="15" value="<?php echo attr($_smarty_tpl->tpl_vars['root_aro_group_id']->value);?>
"></td>
    <td><input type="text" name="root_axo_group_id" size="15" value="<?php echo attr($_smarty_tpl->tpl_vars['root_axo_group_id']->value);?>
"></td>
    <td><b>)</b></td>
  </tr>
  <tr class="controls" align="center">
    <td colspan="10">
    	<input type="submit" class="button" name="action" value="Submit">
    </td>
  </tr>
</table>
<?php if (is_array($_smarty_tpl->tpl_vars['acls']->value) && count($_smarty_tpl->tpl_vars['acls']->value) > 0) {?>
<br />
<table cellpadding="2" cellspacing="2" border="2" width="100%">
  <tr>
    <th rowspan="2" width="4%">ACL ID</th>
    <th colspan="2">ACO</th>
    <th colspan="2">ARO</th>
    <th colspan="2">AXO</th>
    <th colspan="2">ACL</th>
  </tr>
  <tr>
    <th width="12%">Section</th>
    <th width="12%">Value</th>
    <th width="12%">Section</th>
    <th width="12%">Value</th>
    <th width="12%">Section</th>
    <th width="12%">Value</th>
    <th width="8%">Access</th>
    <th width="16%">Updated Date</th>
  </tr>
<?php
$_from = $_smarty_tpl->smarty->ext->_foreach->init($_smarty_tpl, $_smarty_tpl->tpl_vars['acls']->value, 'acl');
$_smarty_tpl->tpl_vars['acl']->do_else = true;
if ($_from !== null) foreach ($_from as $_smarty_tpl->tpl_vars['acl']->value) {
$_smarty_tpl->tpl_vars['acl']->do_else = false;
?>
  <tr valign="top" align="left">
    <td valign="middle" rowspan="2" align="center">
        <?php echo text($_smarty_tpl->tpl_vars['acl']->value['id']);?>

    </td>
    <td nowrap>
		<?php echo text($_smarty_tpl->tpl_vars['acl']->value['aco_section_value']);?>

    </td>
    <td nowrap>
		<?php echo text($_smarty_tpl->tpl_vars['acl']->value['aco_value']);?>

    </td>

    <td nowrap>
		<?php echo text($_smarty_tpl->tpl_vars['acl']->value['aro_section_value']);?>
<br />
    </td>
    <td nowrap>
		<?php echo text($_smarty_tpl->tpl_vars['acl']->value['aro_value']);?>
<br />
    </td>

    <td nowrap>
		<?php echo text($_smarty_tpl->tpl_vars['acl']->value['axo_section_value']);?>
<br />
    </td>
    <td nowrap>
		<?php echo text($_smarty_tpl->tpl_vars['acl']->value['axo_value']);?>
<br />
    </td>

    <td valign="middle" class="<?php if ($_smarty_tpl->tpl_vars['acl']->value['allow']) {?>green<?php } else { ?>red<?php }?>" align="center">
		<?php if ($_smarty_tpl->tpl_vars['acl']->value['allow']) {?>
			ALLOW
		<?php } else { ?>
			DENY
		<?php }?>
    </td>
    <td valign="middle" align="center">
        <?php echo text($_smarty_tpl->tpl_vars['acl']->value['updated_date']);?>

     </td>
  </tr>
  <tr valign="middle" align="left">
    <td colspan="4">
        <b>Return Value:</b> <?php echo text($_smarty_tpl->tpl_vars['acl']->value['return_value']);?>
<br />
    </td>
    <td colspan="4">
        <b>Note:</b> <?php echo text($_smarty_tpl->tpl_vars['acl']->value['note']);?>

    </td>
  </tr>
<?php
}
$_smarty_tpl->smarty->ext->_foreach->restore($_smarty_tpl, 1);?>
</table>
<?php }?>
<input type="hidden" name="return_page" value="<?php echo attr($_smarty_tpl->tpl_vars['return_page']->value);?>
">
</form>
<?php $_smarty_tpl->_subTemplateRender("file:phpgacl/footer.tpl", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), 0, false);
}
}
