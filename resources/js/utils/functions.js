export default function showItemsInCartons(quantity, quantity_per_carton, unit = 'unit'){
  const whole_num = Math.floor(quantity / quantity_per_carton);
  const display_whole_num = (whole_num > 0) ? '<strong>' + whole_num + 'CTN</strong>' : '';
  const modulus = quantity % quantity_per_carton;
  const display_modulus = (modulus > 0) ? '<strong>' + modulus + unit + '</strong>' : '';
  if (display_whole_num === '' && display_modulus === '') {
    return '';
  }
  if (display_whole_num === '' && display_modulus !== '') {
    return display_modulus;
  }
  if (display_whole_num !== '' && display_modulus === '') {
    return display_whole_num;
  }
  return display_whole_num + ' & ' + display_modulus;
}
