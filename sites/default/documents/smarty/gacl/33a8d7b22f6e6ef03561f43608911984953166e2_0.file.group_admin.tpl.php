<?php
/* Smarty version 4.3.4, created on 2025-04-21 11:38:16
  from '/var/www/openemr_tb/gacl/admin/templates/phpgacl/group_admin.tpl' */

/* @var Smarty_Internal_Template $_smarty_tpl */
if ($_smarty_tpl->_decodeProperties($_smarty_tpl, array (
  'version' => '4.3.4',
  'unifunc' => 'content_6806828817d498_74415258',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '33a8d7b22f6e6ef03561f43608911984953166e2' => 
    array (
      0 => '/var/www/openemr_tb/gacl/admin/templates/phpgacl/group_admin.tpl',
      1 => 1700108884,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
    'file:phpgacl/header.tpl' => 1,
    'file:phpgacl/acl_admin_js.tpl' => 1,
    'file:phpgacl/navigation.tpl' => 1,
    'file:phpgacl/footer.tpl' => 1,
  ),
),false)) {
function content_6806828817d498_74415258 (Smarty_Internal_Template $_smarty_tpl) {
$_smarty_tpl->_subTemplateRender("file:phpgacl/header.tpl", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), 0, false);
$_smarty_tpl->_subTemplateRender("file:phpgacl/acl_admin_js.tpl", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), 0, false);
?>
  </head>
  <body>
    <?php $_smarty_tpl->_subTemplateRender("file:phpgacl/navigation.tpl", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), 0, false);
?>
    <form method="post" name="edit_group" action="edit_group.php">
      <input type="hidden" name="csrf_token_form" value="<?php echo attr($_smarty_tpl->tpl_vars['CSRF_TOKEN_FORM']->value);?>
">
      <table cellpadding="2" cellspacing="2" border="2" width="100%">
        <tbody>
          <tr>
            <th width="2%">ID</th>
            <th width="40%">Name</th>
            <th width="20%">Value</th>
            <th width="6%">Objects</th>
            <th width="30%">Functions</th>
            <th width="2%"><input type="checkbox" class="checkbox" name="select_all" onClick="checkAll(this)"/></th>
          </tr>
<?php
$_from = $_smarty_tpl->smarty->ext->_foreach->init($_smarty_tpl, $_smarty_tpl->tpl_vars['groups']->value, 'group');
$_smarty_tpl->tpl_vars['group']->do_else = true;
if ($_from !== null) foreach ($_from as $_smarty_tpl->tpl_vars['group']->value) {
$_smarty_tpl->tpl_vars['group']->do_else = false;
?>
          <tr valign="middle" align="center">
            <td><?php echo text($_smarty_tpl->tpl_vars['group']->value['id']);?>
</td>
                        <td align="left"><?php echo $_smarty_tpl->tpl_vars['group']->value['name'];?>
</td>
            <td align="left"><?php echo text($_smarty_tpl->tpl_vars['group']->value['value']);?>
</td>
            <td><?php echo text($_smarty_tpl->tpl_vars['group']->value['object_count']);?>
</td>
            <td>
              [&nbsp;<a href="assign_group.php?group_type=<?php echo attr_url($_smarty_tpl->tpl_vars['group_type']->value);?>
&group_id=<?php echo attr_url($_smarty_tpl->tpl_vars['group']->value['id']);?>
&return_page=<?php echo attr_url($_smarty_tpl->tpl_vars['return_page']->value);?>
">Assign&nbsp;<?php echo text(mb_strtoupper((string) $_smarty_tpl->tpl_vars['group_type']->value ?? '', 'UTF-8'));?>
</a>&nbsp;]
              [&nbsp;<a href="edit_group.php?group_type=<?php echo attr_url($_smarty_tpl->tpl_vars['group_type']->value);?>
&parent_id=<?php echo attr_url($_smarty_tpl->tpl_vars['group']->value['id']);?>
&return_page=<?php echo attr_url($_smarty_tpl->tpl_vars['return_page']->value);?>
">Add&nbsp;Child</a>&nbsp;]
              [&nbsp;<a href="edit_group.php?group_type=<?php echo attr_url($_smarty_tpl->tpl_vars['group_type']->value);?>
&group_id=<?php echo attr_url($_smarty_tpl->tpl_vars['group']->value['id']);?>
&return_page=<?php echo attr_url($_smarty_tpl->tpl_vars['return_page']->value);?>
">Edit</a>&nbsp;]
              [&nbsp;<a href="acl_list.php?action=Filter&filter_<?php echo attr_url($_smarty_tpl->tpl_vars['group_type']->value);?>
_group=<?php echo attr_url($_smarty_tpl->tpl_vars['group']->value['raw_name']);?>
&return_page=<?php echo attr_url($_smarty_tpl->tpl_vars['return_page']->value);?>
">ACLs</a>&nbsp;]
            </td>
            <td><input type="checkbox" class="checkbox" name="delete_group[]" value="<?php echo attr($_smarty_tpl->tpl_vars['group']->value['id']);?>
"></td>
          </tr>
<?php
}
$_smarty_tpl->smarty->ext->_foreach->restore($_smarty_tpl, 1);?>
          <tr class="controls" align="center">
            <td colspan="4">&nbsp;</td>
            <td colspan="2" nowrap><input type="submit" class="button" name="action" value="Add" /> <input type="submit" class="button" name="action" value="Delete" /></td>
          </tr>
        </tbody>
      </table>
    <input type="hidden" name="group_type" value="<?php echo attr($_smarty_tpl->tpl_vars['group_type']->value);?>
">
    <input type="hidden" name="return_page" value="<?php echo attr($_smarty_tpl->tpl_vars['return_page']->value);?>
">
  </form>
<?php $_smarty_tpl->_subTemplateRender("file:phpgacl/footer.tpl", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), 0, false);
}
}
