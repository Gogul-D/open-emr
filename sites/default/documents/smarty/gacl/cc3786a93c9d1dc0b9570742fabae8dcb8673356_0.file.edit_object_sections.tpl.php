<?php
/* Smarty version 4.3.4, created on 2025-04-21 11:39:35
  from '/var/www/openemr_tb/gacl/admin/templates/phpgacl/edit_object_sections.tpl' */

/* @var Smarty_Internal_Template $_smarty_tpl */
if ($_smarty_tpl->_decodeProperties($_smarty_tpl, array (
  'version' => '4.3.4',
  'unifunc' => 'content_680682d74289f6_87763266',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'cc3786a93c9d1dc0b9570742fabae8dcb8673356' => 
    array (
      0 => '/var/www/openemr_tb/gacl/admin/templates/phpgacl/edit_object_sections.tpl',
      1 => 1700108884,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
    'file:phpgacl/header.tpl' => 1,
    'file:phpgacl/acl_admin_js.tpl' => 1,
    'file:phpgacl/navigation.tpl' => 1,
    'file:phpgacl/pager.tpl' => 4,
    'file:phpgacl/footer.tpl' => 1,
  ),
),false)) {
function content_680682d74289f6_87763266 (Smarty_Internal_Template $_smarty_tpl) {
$_smarty_tpl->_subTemplateRender("file:phpgacl/header.tpl", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), 0, false);
$_smarty_tpl->_subTemplateRender("file:phpgacl/acl_admin_js.tpl", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), 0, false);
?>
  </head>
  <body>
    <?php $_smarty_tpl->_subTemplateRender("file:phpgacl/navigation.tpl", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), 0, false);
?>
    <form method="post" name="edit_object_sections" action="edit_object_sections.php">
      <input type="hidden" name="csrf_token_form" value="<?php echo attr($_smarty_tpl->tpl_vars['CSRF_TOKEN_FORM']->value);?>
">
      <table cellpadding="2" cellspacing="2" border="2" width="100%">
        <tbody>
          <tr class="pager">
            <td colspan="11">
                <?php if ((isset($_smarty_tpl->tpl_vars['paging_data']->value))) {?>
                    <?php $_smarty_tpl->_subTemplateRender("file:phpgacl/pager.tpl", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array('pager_data'=>$_smarty_tpl->tpl_vars['paging_data']->value,'link'=>"?object_type=".((string)$_smarty_tpl->tpl_vars['object_type_escaped']->value)."&"), 0, false);
?>
                <?php } else { ?>
                    <?php $_smarty_tpl->_subTemplateRender("file:phpgacl/pager.tpl", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array('link'=>"?object_type=".((string)$_smarty_tpl->tpl_vars['object_type_escaped']->value)."&"), 0, true);
?>
                <?php }?>
            </td>
          </tr>
          <tr>
            <th width="2%">ID</th>
            <th>Value</th>
            <th>Order</th>
            <th>Name</th>
            <th width="4%">Functions</th>
            <th width="2%"><input type="checkbox" class="checkbox" name="select_all" onClick="checkAll(this)"/></th>
          </tr>
<?php if ((isset($_smarty_tpl->tpl_vars['sections']->value))) {?>
    <?php
$__section_x_0_loop = (is_array(@$_loop=$_smarty_tpl->tpl_vars['sections']->value) ? count($_loop) : max(0, (int) $_loop));
$__section_x_0_total = $__section_x_0_loop;
$_smarty_tpl->tpl_vars['__smarty_section_x'] = new Smarty_Variable(array());
if ($__section_x_0_total !== 0) {
for ($__section_x_0_iteration = 1, $_smarty_tpl->tpl_vars['__smarty_section_x']->value['index'] = 0; $__section_x_0_iteration <= $__section_x_0_total; $__section_x_0_iteration++, $_smarty_tpl->tpl_vars['__smarty_section_x']->value['index']++){
?>
          <tr valign="top" align="center">
            <td>
              <?php echo text($_smarty_tpl->tpl_vars['sections']->value[(isset($_smarty_tpl->tpl_vars['__smarty_section_x']->value['index']) ? $_smarty_tpl->tpl_vars['__smarty_section_x']->value['index'] : null)]['id']);?>

              <input type="hidden" name="sections[<?php echo attr($_smarty_tpl->tpl_vars['sections']->value[(isset($_smarty_tpl->tpl_vars['__smarty_section_x']->value['index']) ? $_smarty_tpl->tpl_vars['__smarty_section_x']->value['index'] : null)]['id']);?>
][]" value="<?php echo attr($_smarty_tpl->tpl_vars['sections']->value[(isset($_smarty_tpl->tpl_vars['__smarty_section_x']->value['index']) ? $_smarty_tpl->tpl_vars['__smarty_section_x']->value['index'] : null)]['id']);?>
">
            </td>
            <td><input type="text" size="10" name="sections[<?php echo attr($_smarty_tpl->tpl_vars['sections']->value[(isset($_smarty_tpl->tpl_vars['__smarty_section_x']->value['index']) ? $_smarty_tpl->tpl_vars['__smarty_section_x']->value['index'] : null)]['id']);?>
][]" value="<?php echo attr($_smarty_tpl->tpl_vars['sections']->value[(isset($_smarty_tpl->tpl_vars['__smarty_section_x']->value['index']) ? $_smarty_tpl->tpl_vars['__smarty_section_x']->value['index'] : null)]['value']);?>
"></td>
            <td><input type="text" size="10" name="sections[<?php echo attr($_smarty_tpl->tpl_vars['sections']->value[(isset($_smarty_tpl->tpl_vars['__smarty_section_x']->value['index']) ? $_smarty_tpl->tpl_vars['__smarty_section_x']->value['index'] : null)]['id']);?>
][]" value="<?php echo attr($_smarty_tpl->tpl_vars['sections']->value[(isset($_smarty_tpl->tpl_vars['__smarty_section_x']->value['index']) ? $_smarty_tpl->tpl_vars['__smarty_section_x']->value['index'] : null)]['order']);?>
"></td>
            <td><input type="text" size="40" name="sections[<?php echo attr($_smarty_tpl->tpl_vars['sections']->value[(isset($_smarty_tpl->tpl_vars['__smarty_section_x']->value['index']) ? $_smarty_tpl->tpl_vars['__smarty_section_x']->value['index'] : null)]['id']);?>
][]" value="<?php echo attr($_smarty_tpl->tpl_vars['sections']->value[(isset($_smarty_tpl->tpl_vars['__smarty_section_x']->value['index']) ? $_smarty_tpl->tpl_vars['__smarty_section_x']->value['index'] : null)]['name']);?>
"></td>
            <td>&nbsp;</td>
            <td><input type="checkbox" class="checkbox" name="delete_sections[]" value="<?php echo attr($_smarty_tpl->tpl_vars['sections']->value[(isset($_smarty_tpl->tpl_vars['__smarty_section_x']->value['index']) ? $_smarty_tpl->tpl_vars['__smarty_section_x']->value['index'] : null)]['id']);?>
"></td>
          </tr>
    <?php
}
}
}?>
          <tr class="pager">
            <td colspan="6">
                <?php if ((isset($_smarty_tpl->tpl_vars['paging_data']->value))) {?>
                    <?php $_smarty_tpl->_subTemplateRender("file:phpgacl/pager.tpl", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array('pager_data'=>$_smarty_tpl->tpl_vars['paging_data']->value,'link'=>"?object_type=".((string)$_smarty_tpl->tpl_vars['object_type_escaped']->value)."&"), 0, true);
?>
                <?php } else { ?>
                    <?php $_smarty_tpl->_subTemplateRender("file:phpgacl/pager.tpl", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array('link'=>"?object_type=".((string)$_smarty_tpl->tpl_vars['object_type_escaped']->value)."&"), 0, true);
?>
                <?php }?>
            </td>
          </tr>
          <tr class="spacer">
            <td colspan="6"></td>
          </tr>
          <tr align="center">
            <td colspan="6"><b>Add <?php echo text(mb_strtoupper((string) $_smarty_tpl->tpl_vars['object_type']->value ?? '', 'UTF-8'));?>
 Sections</b></td>
          </tr>
          <tr>
            <th>ID</th>
            <th>Value</th>
            <th>Order</th>
            <th>Name</th>
            <th>Functions</th>
            <th>&nbsp;</td>
          </tr>
<?php if ((isset($_smarty_tpl->tpl_vars['new_sections']->value))) {?>
    <?php
$__section_y_1_loop = (is_array(@$_loop=$_smarty_tpl->tpl_vars['new_sections']->value) ? count($_loop) : max(0, (int) $_loop));
$__section_y_1_total = $__section_y_1_loop;
$_smarty_tpl->tpl_vars['__smarty_section_y'] = new Smarty_Variable(array());
if ($__section_y_1_total !== 0) {
for ($__section_y_1_iteration = 1, $_smarty_tpl->tpl_vars['__smarty_section_y']->value['index'] = 0; $__section_y_1_iteration <= $__section_y_1_total; $__section_y_1_iteration++, $_smarty_tpl->tpl_vars['__smarty_section_y']->value['index']++){
?>
          <tr valign="top" align="center">
            <td>N/A</td>
            <td><input type="text" size="10" name="new_sections[<?php echo attr($_smarty_tpl->tpl_vars['new_sections']->value[(isset($_smarty_tpl->tpl_vars['__smarty_section_y']->value['index']) ? $_smarty_tpl->tpl_vars['__smarty_section_y']->value['index'] : null)]['id']);?>
][]" value=""></td>
            <td><input type="text" size="10" name="new_sections[<?php echo attr($_smarty_tpl->tpl_vars['new_sections']->value[(isset($_smarty_tpl->tpl_vars['__smarty_section_y']->value['index']) ? $_smarty_tpl->tpl_vars['__smarty_section_y']->value['index'] : null)]['id']);?>
][]" value=""></td>
            <td><input type="text" size="40" name="new_sections[<?php echo attr($_smarty_tpl->tpl_vars['new_sections']->value[(isset($_smarty_tpl->tpl_vars['__smarty_section_y']->value['index']) ? $_smarty_tpl->tpl_vars['__smarty_section_y']->value['index'] : null)]['id']);?>
][]" value=""></td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
          </tr>
    <?php
}
}
}?>
          <tr class="controls" align="center">
            <td colspan="4">
              <input type="submit" class="button" name="action" value="Submit"> <input type="reset" class="button" value="Reset">
            </td>
            <td colspan="2">
              <input type="submit" class="button" name="action" value="Delete">
            </td>
          </tr>
        </tbody>
      </table>
    <input type="hidden" name="object_type" value="<?php echo attr($_smarty_tpl->tpl_vars['object_type']->value);?>
">
    <input type="hidden" name="return_page" value="<?php echo attr($_smarty_tpl->tpl_vars['return_page']->value);?>
">
    </form>
<?php $_smarty_tpl->_subTemplateRender("file:phpgacl/footer.tpl", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), 0, false);
}
}
