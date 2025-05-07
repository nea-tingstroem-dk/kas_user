{* HEADER *}

<table>
  <thead>
    <tr>
      <th>Reg.</th>
      <th>Mødt</th>
      <th>Afbud</th>
      <th>Konto</th>
      <th>Navn</th>
    </tr>
  </thead>
{foreach from=$participants key=id item=participant}
  <tr>
    <td>
      <input type="radio" id="deltager_{$id}" name="part_status_{$id}" value="" >
    </td>
    <td>
      <input type="radio" id="afbud_{$id}" name="part_status_{$id}" >
    </td>
    <td>
      <input type="radio" id="moedt_{$id}" name="part_status_{$id}" >
    </td>
    <td>{$participant.konto}</td>
    <td>{$participant.name}</td>
  </tr>
{/foreach}
</table>
{* FIELD EXAMPLE: OPTION 2 (MANUAL LAYOUT)

  <div>
    <span>{$form.favorite_color.label}</span>
    <span>{$form.favorite_color.html}</span>
  </div>

{* FOOTER *}
<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
{literal}
  <script type="text/javascript">
    CRM.$(function ($) {
      
    });
  </script>
{/literal}
