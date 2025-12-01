(function($){
  function fmtDate(d){ return d ? d : '—'; }
  function downloadSvg(){
    return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a1 1 0 011 1v9.586l2.293-2.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L11 13.586V4a1 1 0 011-1zm-7 16a1 1 0 011-1h12a1 1 0 110 2H6a1 1 0 01-1-1z"/></svg>';
  }
  function renderRows($tbody, rows){
    $tbody.empty();
    if(!rows || rows.length === 0){
      $tbody.append('<tr><td colspan="5" class="guc-empty">Sin registros.</td></tr>');
      return;
    }
    rows.forEach(function(r, idx){
      var pdf = r.pdf_url ? '<a class="guc-icon-btn" href="'+r.pdf_url+'" target="_blank" title="Descargar">'+downloadSvg()+'</a>' : '';
      var tr = '<tr>' +
        '<td>'+(idx+1)+'</td>' +
        '<td>'+(r.situacion || '—')+'</td>' +
        '<td>'+fmtDate(r.fecha)+'</td>' +
        '<td>'+(r.motivo || '—')+'</td>' +
        '<td>'+pdf+'</td>' +
      '</tr>';
      $tbody.append(tr);
    });
  }
  function showEmpty($wrap){
    var html = '<div class="guc-empty-state">'
             + '<span class="guc-empty-icon">⚠️</span>'
             + '<span class="guc-empty-text">Su caso aun no ha sido registrado, por favor inténtelo más tarde.</span>'
             + '</div>';
    $wrap.html(html);
  }
  function init(){
    var $wrap = $('#guc-cliente[data-ready="0"]');
    if(!$wrap.length) return;
    $.get(GUC_CLIENTE.ajaxurl, { action:'guc_cliente_dataset', _wpnonce:GUC_CLIENTE.nonce })
      .done(function(res){
        if(!res || res.success === false){
          showEmpty($wrap);
          return;
        }
        var data = res.data;
        if(!data || !data.case){
          showEmpty($wrap);
          return;
        }
        $wrap.find('[data-k="entity"]').text(data.case.entidad || '—');
        $wrap.find('[data-k="objeto"]').text(data.case.objeto || '—');
        $wrap.find('[data-k="nomenclatura"]').text(data.case.nomenclatura || '—');
        $wrap.find('[data-k="descripcion"]').text(data.case.descripcion || '—');
        $wrap.find('[data-k="convocatoria"]').text(data.case.convocatoria || '—');
        $('#sec-label').text(data.sec_label);

        renderRows($('#tbl-pre tbody'), data.pre);
        renderRows($('#tbl-secretaria tbody'), data.secretaria);
        renderRows($('#tbl-arbitral tbody'), data.arbitral);
        $wrap.attr('data-ready','1');
      })
      .fail(function(err){
        console.error(err);
        showEmpty($('#guc-cliente'));
      });
  }
  $(document).ready(init);
})(jQuery);
