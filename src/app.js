Vue.component('hit', {
  template:'<div class="wssearch--hit" >'+
               '<span class="wssearch--hit__namespace" v-if="hit._source.subject.namespacename" >{{hit._source.subject.namespacename}}</span>'+
               '<span class="wssearch--hit__pagetitle" >{{hit._source.subject.title}}</span>'+
               '<span class="wssearch--hit__date" >{{date}}</span>'+
               '<template v-for="(hitID, key) in $root.hitIDs">'+
                  '<span class="wssearch--hit__link" v-if="Object.keys($root.hitIDs)[0] == key"><a  v-bind:href="href" >{{hit._source["P:" + key ][hitID][0]}}</a></span>'+
                  '<span v-bind:class="\'wssearch--hit__property\' + key" v-else >{{hit._source["P:" + key ][hitID][0]}}</span>'+
               '</template>'+
               '<img v-if="img" class="wssearch--hit__img" v-bind:src="img"/>'+
               '<span class="wssearch--hit__body" v-html="exerpt"></span>'+
             '</div>',
  props:{
    hit:Object
  },
    computed:{
      img:function(){
        if(this.hit._source.file_path){
          if(this.hit._source.attachment && this.hit._source.attachment.content_type == "application/pdf"){
              return '/img_auth.php/thumb/'+ this.hit._source.subject.title +'/page1-600px-'+ this.hit._source.subject.title +'.jpg';
          }else{
              return this.hit._source.file_path;
          }
        }
      },
      date:function(){
        var unsplit = this.hit._source["P:29"].dat_raw[0];
        var rawz = unsplit.split("/");
        var ndate = this.$root.monthnames[rawz[2] - 1] + ' ' + rawz[3] + ', ' + rawz[1];
        return ndate;

      },
    exerpt:function(){
      if(this.hit.highlight){
          if(this.hit.highlight['text_raw']){
            return this.hit.highlight['text_raw'][0];
          }else if(this.hit.highlight['attachment.content']){
            return this.hit.highlight['attachment.content'][0];
          }
        }
    },
    href:function(){
    if(this.hit._source.subject.namespacename){
       return "/" + this.hit._source.subject.namespacename + ":" + this.hit._source.subject.title;
     }else{
       return "/" + this.hit._source.subject.title;
     }
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
  template:'<li v-show="show" >'+
              '<label v-bind:class="labelclass" >'+
                  '<input type="checkbox" v-bind:id="createid" @change="filter" v-model="$root.selected" v-bind:value="val">'+
                  '<span class="wssearch--filter-title" >{{title}}</span>'+
                  '<span class="wssearch--filter-count" >{{agg.doc_count}}</span>'+
               '</label>'+
            '</li>',
  props:{
    agg:Object,
    name:String,
    index:Number
  },
  computed:{
    labelclass:function(){
      return "wssearch--filter__" + this.name.toLowerCase() +"--"+ this.agg.key.toLowerCase().replace(" ", "_")
    },
    title:function(){
      if(this.agg.name){
        return this.agg.name;
      }else{
        return this.agg.key;
      }
    },
    createid:function(){
      return this.name.toLowerCase().replace(" ", "_") +"--"+ this.agg.key.toLowerCase().replace(" ", "_")
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
        return  { "value" : this.agg.key , "key": this.name,  "range": { "P:29.datField": { "gte": Number(this.agg.from+'.0000000'), "lte": Number(this.agg.to+'.0000000')}}};
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
      this.$root.api(0, this.$root.term);
    }
  }
});

// dev mode, replace with "var app ="
window.app = new Vue({ el: "#app",
  data:{
    total: vueinitdata.total,
    hits: vueinitdata.hits,
    aggs: vueinitdata.aggs,
    size:10,
    from: 0,
    selected: vueinitdata.selected ,
    open: [],
    monthnames: ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep", "Oct","Nov","Dec"],
    rangeFrom: 0,
    rangeTo: 80,
    dropdown: [],
    term: vueinitdata.term ,
    loading: false ,
    dates:vueinitdata.dates,
    filterIDs:vueinitdata.filterIDs,
    hitIDs:vueinitdata.hitIDs
    },

  methods:{
    api:function(from, term){

      var root = this;
      root.loading = true;
      root.from = from;
      root.term = term


      history.pushState({page: 1}, "title 1", this.urlstring() );

      var params = {
        action: 'query',
        meta: 'WSSearch',
        format: 'json',
        smfilter: JSON.stringify(root.selected),
        smterm:root.term,
        smfrom:root.from,
        smpage:mw.config.values.wgPageName,
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
    },
    urlstring:function(){

      var url = "?";

      if(this.from > 0){
        url += "&offset="+ this.from;
      }

      if(this.term){
        url += "&term="+ this.term;
      }

     if(this.selected.length){
       url += "&filters=";
       var filtersarray = [];
      this.selected.forEach(function(item, i){
        if(item.range){
          filtersarray.push("range_"+item.value+"_"+item.key+"-"+item.range["P:29.datField"].gte+"_"+item.range["P:29.datField"].lte);
        }else{
          filtersarray.push(""+item.key+"-"+item.value+"");
        }
      });
      url += filtersarray.join("~");
    }
      return encodeURI(url);
    },
    clearfilters:function(e){
      this.selected = [];
      this.api(0, this.term);
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
      this.api(0, e.target.value);
    },
    activepage:function(pager){
      if(pager == (this.from / this.size) + 1){
        return 'active';
      }
    },
    next:function(e){
      var root = this;
      root.loading = true;
      if(e.target.innerText.trim() == '<'){
        this.from = this.from - this.size;
      }else if(e.target.innerText.trim() == '>'){
        this.from = this.from + this.size;
      }else{
        this.from = Math.ceil(this.size * (e.target.innerText - 1));
      }
      this.api(this.from, root.term);
    }
  },
  computed:{
    mainloading:function(){
      if(this.loading){
        return 'wssearch--main wssearch--main__loading';
      }else{
        return 'wssearch--main';
      }

    },
    sort:function(){
      this.aggs.Date.buckets = this.aggs.Date.buckets.filter(function(el){
        if(el.doc_count > 0){
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
    }
  }
});
