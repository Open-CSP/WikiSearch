methods:{
  search:function(e){
    var root = this;
   this.term = e.target.value;
    var params = {
        action: 'query',
        meta: 'smws',
        format: 'json',
        smfilter: JSON.stringify(root.selected),
        smtitle: root.titleID,
        smexerpt: root.exerptID,
        smaggs: Object.keys(root.aggs).join(),
        smclass:root.main,
        smterm:e.target.value


       },
   api = new mw.Api();

   api.post(  params ).done( function ( data ) {
    console.log( data );
    root.total = data.result.total;
    root.hits = JSON.parse(data.result.hits);
    root.aggs = JSON.parse(data.result.aggs);



  });

},
nextz:function(e){
  console.log('tes');
  var root = this;
  var params = {
      action: 'query',
      meta: 'smws',
      format: 'json',
      smscroll: root.scroll,
     },
 api = new mw.Api();

 api.post(  params ).done( function ( data ) {
  console.log( data );

  root.hits = JSON.parse(data.result.hits);



});

}

}
