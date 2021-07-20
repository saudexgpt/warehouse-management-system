export default function showItemsInCartons(quantity, quantity_per_carton){
  const whole_num = Math.floor(quantity / quantity_per_carton);
  const display_whole_num = (whole_num > 0) ? '<strong>' + whole_num + '</strong>' : '';
  const modulus = quantity % quantity_per_carton;
  const fraction = (modulus > 0) ? '&nbsp;<small>' + modulus + '/' + quantity_per_carton + '</small>' : '';
  if (display_whole_num === '' && fraction === '') {
    return '';
  }
  return display_whole_num + fraction + ' CTN';
}
