methods:{
  more:function(prop){
var index = this.open.indexOf(prop);
  if (index > -1) {
    this.open.splice(index, 1);
  }else{
    this.open.push(prop);
     }
  },
  search:function(e){
    var root = this;
    root.loading = true;
    this.from = 0;
   this.term = e.target.value;
    var params = {
        action: 'query',
        meta: 'smws',
        format: 'json',
        smfilter: JSON.stringify(root.selected),
        smtitle: root.titleID,
        smexerpt: root.exerpt,
        smaggs: Object.keys(root.aggs).join(),
        smclass:root.main,
        smterm:e.target.value,
          smdates:JSON.stringify(root.dates)


       },
   api = new mw.Api();

   api.post(  params ).done( function ( data ) {
    console.log( data );
    root.total = data.result.total;
    root.hits = JSON.parse(data.result.hits);
    root.aggs = JSON.parse(data.result.aggs);
    root.loading = false;



  });

},
activepage:function(pager){
  if(pager == (this.from / this.size) + 1){
  return 'active';
}
},
nextz:function(e){
  var root = this;
 root.loading = true;




   if(e.target.innerText.trim() == '<'){
     console.log('back');

    this.from = this.from - this.size;
  }else if(e.target.innerText.trim() == '>'){
        this.from = this.from + this.size;
  }else{
     console.log('nr');
  this.from = Math.ceil(this.size * (e.target.innerText - 1));
}


var params = {
    action: 'query',
    meta: 'smws',
    format: 'json',
    smfilter: JSON.stringify(root.selected),
    smtitle: root.titleID,
    smexerpt: root.exerpt,
    smaggs: Object.keys(root.aggs).join(),
    smclass:root.main,
    smfrom:this.from,
    smterm:root.term,
    smdates:JSON.stringify(root.dates)




   },
api = new mw.Api();

api.post(  params ).done( function ( data ) {
console.log( data );
root.total = data.result.total;
root.hits = JSON.parse(data.result.hits);
root.aggs = JSON.parse(data.result.aggs);
root.loading = false;


});




}

},
computed:{
  mainloading:function(){
    if(this.loading){
      return 'smws--main smws--loading';
    }else{
      return 'smws--main';
    }

  },
  sort:function(){
    this.aggs.Date.buckets = this.aggs.Date.buckets.filter(function(el){

      if(el.doc_count > 0){
        console.log(el);
        return el;
      }
    }).reverse();


  },
  pagers:function(e){
  if(this.total >= this.size){
    if(this.from == 0){
      var pages = [];
    }else{
      var pages = ['<'];
    }
    var i;
    var step = Math.ceil(this.total / this.size);
    for (i = 0; i < step; i++) {
      pages.push(i + 1)
    }
    if(this.from + this.size >=  this.total - this.size){

    }else{
      pages.push('>');
    }

      return pages;
    }
  },


}
