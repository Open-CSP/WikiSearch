Vue.component('hit', {
  template:'<div><a v-bind:href="href">{{title}}</a><br><small v-html="exerpt"></small></div>',
  props:{
    hit:Object
  },
  computed:{
    title:function(){
      if(this.$root.titleID && this.hit._source['P:'+ this.$root.titleID]){
        if(this.hit._source['P:'+ this.$root.titleID].txtField){
          return this.hit._source['P:'+ this.$root.titleID].txtField[0]
        }else if(this.hit._source['P:'+ this.$root.titleID].wpgField) {
          return this.hit._source['P:'+ this.$root.titleID].wpgField[0]
        }else{
          return 'set exerpt property'
        }
      }else{
        return '?';
      }
    },
    exerpt:function(){

      console.log(this.hit.highlight);

      if(this.hit.highlight){

        if(this.hit.highlight['P:'+ this.$root.exerptID + '.txtField']){
          return this.hit.highlight['P:'+ this.$root.exerptID + '.txtField'][0];
        }else if(this.hit.highlight['P:'+ this.$root.exerptID + '.wpgField']){
          return this.hit.highlight['P:'+ this.$root.exerptID + '.wpgField'][0];
        }else{
            if(this.hit.highlight['text_raw']){
          return this.hit.highlight['text_raw'][0];
        }
        }
      }else{
      }
    },
    href:function(){
      return this.hit._source.subject.title
    }
  },
  data(){
    return {
      hoi:'hoi'
    }
  },
  methods:{

  }
});


Vue.component('agg', {
  template:`<li v-show="show" ><label><input type="checkbox"  @change="filter" v-model="$root.selected" v-bind:value="val"> {{title}} ({{agg.doc_count}})</label></li>`,
  props:{
    agg:Object,
    name:String,
    index:Number
  },
  computed:{
    title:function(){
      if(this.agg.name){
        return this.agg.name;
      }else{
        return this.agg.key;
      }
    },
    show:function(){
      if(this.index < 5 || this.$root.open.includes(this.name)){
        if(this.agg.doc_count > 0){
          return true;
        }
      }
    },
    val:function(){
      if(this.name == 'Date' ){
        return  { "range": { "P:29.datField": { "gte": Number(this.agg.from+'.0000000'), "lte": Number(this.agg.to+'.0000000')}}};
      }else{
        return {value:this.agg.key, key:this.name};
      }
    }
  },
  data(){
    return {
      hoi:'hoi'
    }
  },
  methods:{
    filter:function(e){



      var root = this.$root;
      root.loading = true;
      root.from = 0;
      var params = {
        action: 'query',
        meta: 'smws',
        format: 'json',
        smfilter: JSON.stringify(root.selected),
        smtitle: root.titleID,
        smexerpt: root.exerpt,
        smaggs: root.orgaggs,
        smclass:root.main,
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



      })

    }
  }
});


var app = new Vue({ el: "#app", data:{
  total: vueinitdata.total,
  hits: vueinitdata.hits,
  aggs: vueinitdata.aggs ,
  orgaggs: vueinitdata.orgaggs ,
  size:10,
  from: 0,
  selected: [],
  open: [],
  rangeFrom: 0,
  rangeTo: 80,
  dropdown: [],
  term: "" ,
  loading: false ,
  dates:vueinitdata.dates,
  main: vueinitdata.main,
  filterIDs:vueinitdata.filterIDs,
  exerptID: vueinitdata.exerptID,
  exerpt: vueinitdata.exerpt,
  titleID: vueinitdata.titleID
}, methods:{
  getNs:function(e){
    console.log(e.target.value);
    var params = {
      action: 'ask',
      query: '[Name::~~*'+e.target.value+'*][[Class::Space]]|?namespace|?Name|sort=Name|order=asc',
      format: 'json'

    },
    api = new mw.Api();

    api.post(  params ).done( function ( data ) {
      console.log(data);
    });

  },
  setrange:function(e, pos){
    this[pos] = e.target.value;
  },
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
      smaggs: root.orgaggs,
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
      smaggs: root.orgaggs,
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
  rangeName:function(){
    var chunksize = 100 / this.aggs.Date.buckets.length;
    return this.aggs.Date.buckets[Math.round(this.rangeTo / chunksize)].key;

  },
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
});
