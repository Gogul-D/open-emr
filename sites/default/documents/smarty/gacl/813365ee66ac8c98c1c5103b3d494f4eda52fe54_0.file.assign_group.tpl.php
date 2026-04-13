<?php
/* Smarty version 4.3.4, created on 2025-04-21 11:38:27
  from '/var/www/openemr_tb/gacl/admin/templates/phpgacl/assign_group.tpl' */

/* @var Smarty_Internal_Template $_smarty_tpl */
if ($_smarty_tpl->_decodeProperties($_smarty_tpl, array (
  'version' => '4.3.4',
  'unifunc' => 'content_680682937a0516_91516999',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '813365ee66ac8c98c1c5103b3d494f4eda52fe54' => 
    array (
      0 => '/var/www/openemr_tb/gacl/admin/templates/phpgacl/assign_group.tpl',
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
function content_680682937a0516_91516999 (Smarty_Internal_Template $_smarty_tpl) {
$_smarty_tpl->_checkPlugins(array(0=>array('file'=>'/var/www/openemr_tb/vendor/smarty/smarty/libs/plugins/function.html_options.php','function'=>'smarty_function_html_options',),));
$_smarty_tpl->_subTemplateRender("file:phpgacl/header.tpl", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), 0, false);
echo '<script'; ?>
>
<?php echo $_smarty_tpl->tpl_vars['js_array']->value;?>

<?php echo '</script'; ?>
>
<?php $_smarty_tpl->_subTemplateRender("file:phpgacl/acl_admin_js.tpl", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), 0, false);
?>
  </head>
  <body onload="populate(document.assign_group.<?php echo $_smarty_tpl->tpl_vars['group_type']->value;?>
_section,document.assign_group.elements['objects[]'], '<?php echo $_smarty_tpl->tpl_vars['js_array_name']->value;?>
')">
    <?php $_smarty_tpl->_subTemplateRender("file:phpgacl/navigation.tpl", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), 0, false);
?>
    <form method="post" name="assign_group" action="assign_group.php">
      <input type="hidden" name="csrf_token_form" value="<?php echo attr($_smarty_tpl->tpl_vars['CSRF_TOKEN_FORM']->value);?>
">
      <table cellpadding="2" cellspacing="2" border="2" width="100%">
        <tbody>
          <tr>
            <th width="32%">Sections</th>
            <th width="32%"><?php echo text($_smarty_tpl->tpl_vars['object_type']->value);?>
s</th>
            <th width="4%">&nbsp;</th>
            <th width="32%">Selected</th>
          </tr>
          <tr valign="top" align="center">
            <td>
              [ <a href="edit_object_sections.php?object_type=<?php echo attr_url($_smarty_tpl->tpl_vars['group_type']->value);?>
&return_page=<?php echo attr_url($_smarty_tpl->tpl_vars['return_page']->value);?>
">Edit</a> ]
              <br />
              <select name="<?php echo attr($_smarty_tpl->tpl_vars['group_type']->value);?>
_section" tabindex="0" size="10" width="200" onclick="populate(document.assign_group.<?php echo attr($_smarty_tpl->tpl_vars['group_type']->value);?>
_section,document.assign_group.elements['objects[]'],'<?php echo $_smarty_tpl->tpl_vars['js_array_name']->value;?>
')">
                <?php echo smarty_function_html_options(array('options'=>$_smarty_tpl->tpl_vars['options_sections']->value,'selected'=>$_smarty_tpl->tpl_vars['section_value']->value),$_smarty_tpl);?>

              </select>
            </td>
            <td>
              [ <a href="javascript: location.href = 'edit_objects.php?object_type=<?php echo attr_url($_smarty_tpl->tpl_vars['group_type']->value);?>
&section_value=' + document.assign_group.<?php echo attr_url($_smarty_tpl->tpl_vars['group_type']->value);?>
_section.options[document.assign_group.<?php echo attr_url($_smarty_tpl->tpl_vars['group_type']->value);?>
_section.selectedIndex].value + '&return_page=<?php echo attr_url($_smarty_tpl->tpl_vars['return_page']->value);?>
';">Edit</a> ]
              [ <a href="#" onClick="window.open('object_search.php?src_form=assign_group&object_type=<?php echo attr($_smarty_tpl->tpl_vars['group_type']->value);?>
&section_value=' + document.assign_group.<?php echo attr($_smarty_tpl->tpl_vars['group_type']->value);?>
_section.options[document.assign_group.<?php echo attr($_smarty_tpl->tpl_vars['group_type']->value);?>
_section.selectedIndex].value,'','status=yes,width=400,height=400','','status=yes,width=400,height=400');">Search</a> ]
              <br />
              <select name="objects[]" tabindex="0" size="10" width="200" multiple>
              </select>
            </td>
            <td valign="middle">
              <br /><input type="button" class="select" name="select" value="&nbsp;&gt;&gt;&nbsp;" onClick="select_item(document.assign_group.<?php echo attr($_smarty_tpl->tpl_vars['group_type']->value);?>
_section, document.assign_group.elements['objects[]'], document.assign_group.elements['selected_<?php echo attr($_smarty_tpl->tpl_vars['group_type']->value);?>
[]'])">
              <br /><input type="button" class="deselect" name="deselect" value="&nbsp;&lt;&lt;&nbsp;" onClick="deselect_item(document.assign_group.elements['selected_<?php echo attr($_smarty_tpl->tpl_vars['group_type']->value);?>
[]'])">
            </td>
            <td>
              <br />
              <select name="selected_<?php echo attr($_smarty_tpl->tpl_vars['group_type']->value);?>
[]" tabindex="0" size="10" width="200" multiple>
				<?php echo smarty_function_html_options(array('options'=>$_smarty_tpl->tpl_vars['options_selected_objects']->value,'selected'=>$_smarty_tpl->tpl_vars['selected_object']->value),$_smarty_tpl);?>

              </select>
            </td>
          </tr>
          <tr class="controls" align="center">
            <td colspan="4">
              <input type="submit" class="button" name="action" value="Submit"> <input type="reset" class="button" value="Reset">
            </td>
          </tr>
        </tbody>
      </table>
      <br />
      <table cellpadding="2" cellspacing="2" border="2" width="100%">
        <tr align="center">
	      <td colspan="5"><b><?php echo text($_smarty_tpl->tpl_vars['total_objects']->value);?>
</b> <?php echo text(mb_strtoupper((string) $_smarty_tpl->tpl_vars['group_type']->value ?? '', 'UTF-8'));?>
s in Group: <b><?php echo text($_smarty_tpl->tpl_vars['group_name']->value);?>
</b></td>
        </tr>
        <tr class="pager">
          <td colspan="5">
        <?php $_smarty_tpl->_subTemplateRender("file:phpgacl/pager.tpl", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array('pager_data'=>$_smarty_tpl->tpl_vars['paging_data']->value,'link'=>"?group_type=".((string)$_smarty_tpl->tpl_vars['group_type_escaped']->value)."&group_id=".((string)$_smarty_tpl->tpl_vars['group_id_escaped']->value)."&"), 0, false);
?>
          </td>
        </tr>
        <tr>
	<th>Section</th>
	<th><?php echo text($_smarty_tpl->tpl_vars['object_type']->value);?>
</th>
	<th><?php echo text(mb_strtoupper((string) $_smarty_tpl->tpl_vars['group_type']->value ?? '', 'UTF-8'));?>
 Value</th>
	<th width="4%">Functions</th>
	<th width="2%"><input type="checkbox" class="checkbox" name="select_all" onClick="checkAll(this)"/></th>
        </tr>
<?php
$_from = $_smarty_tpl->smarty->ext->_foreach->init($_smarty_tpl, $_smarty_tpl->tpl_vars['rows']->value, 'row');
$_smarty_tpl->tpl_vars['row']->do_else = true;
if ($_from !== null) foreach ($_from as $_smarty_tpl->tpl_vars['row']->value) {
$_smarty_tpl->tpl_vars['row']->do_else = false;
?>
  <tr valign="top" align="center">
    <td>
      <?php echo text($_smarty_tpl->tpl_vars['row']->value['section']);?>

    </td>
    <td>
      <?php echo text($_smarty_tpl->tpl_vars['row']->value['name']);?>

    </td>
    <td>
      <?php echo text($_smarty_tpl->tpl_vars['row']->value['value']);?>

    </td>
    <td>
      [ <a href="acl_list.php?action=Filter&filter_<?php echo attr_url($_smarty_tpl->tpl_vars['group_type']->value);?>
_section=<?php echo attr_url($_smarty_tpl->tpl_vars['row']->value['section_value']);?>
&filter_<?php echo attr_url($_smarty_tpl->tpl_vars['group_type']->value);?>
=<?php echo attr_url($_smarty_tpl->tpl_vars['row']->value['name']);?>
&return_page=<?php echo attr_url($_smarty_tpl->tpl_vars['return_page']->value);?>
">ACLs</a> ]
    </td>
    <td>
      <input type="checkbox" class="checkbox" name="delete_assigned_object[]" value="<?php echo attr($_smarty_tpl->tpl_vars['row']->value['section_value']);?>
^<?php echo attr($_smarty_tpl->tpl_vars['row']->value['value']);?>
">
    </td>
  </tr>
<?php
}
$_smarty_tpl->smarty->ext->_foreach->restore($_smarty_tpl, 1);?>
  <tr class="pager">
    <td colspan="5">
      <?php $_smarty_tpl->_subTemplateRender("file:phpgacl/pager.tpl", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array('pager_data'=>$_smarty_tpl->tpl_vars['paging_data']->value,'link'=>"?"), 0, true);
?>
    </td>
  </tr>
  <tr class="controls" align="center">
    <td colspan="3">&nbsp;</td>
    <td colspan="2">
      <input type="submit" class="button" name="action" value="Remove">
    </td>
  </tr>
</table>
<input type="hidden" name="group_id" value="<?php echo attr($_smarty_tpl->tpl_vars['group_id']->value);?>
">
<input type="hidden" name="group_type" value="<?php echo attr($_smarty_tpl->tpl_vars['group_type']->value);?>
">
<input type="hidden" name="return_page" value="<?php echo attr($_smarty_tpl->tpl_vars['return_page']->value);?>
">
</form>
<?php $_smarty_tpl->_subTemplateRender("file:phpgacl/footer.tpl", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), 0, false);
}
}
