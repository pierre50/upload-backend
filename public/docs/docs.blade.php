<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width">
    <title>API doc</title>
    <script src="https://unpkg.com/vue"></script>
    <script src="https://unpkg.com/element-ui@2.0.4/lib/index.js"></script>
    <script src="https://unpkg.com/element-ui/lib/umd/locale/fr.js"></script>
    <script type="text/javascript" src="/docs/export.json"></script>
    <link href="https://unpkg.com/element-ui@2.0.4/lib/theme-chalk/index.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Noto+Sans" rel="stylesheet">
    <link href="https://netdna.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link  href="/docs/styles.css" rel="stylesheet" type="text/css"/>
    <script>
      ELEMENT.locale(ELEMENT.lang.fr)
    </script>
  </head>
  <body>
    <div id='app'>
      <el-container>
        <el-header>
          <h2>Supfile - API doc</h2>
        </el-header>
        <el-main v-if="requests">
          <el-collapse :value="requests">
            <el-collapse-item v-for="request in requests">
              <template slot="title">
                <div class="request-header">
                  <el-tag :type="request.tag">{{ request.method }}</el-tag>
                  <div class="request-header-title">{{ request.name }}</div>
                </div>
              </template>
              <div class="request">
                <div class="bg-dark request-url">{{ request.url }}</div>
                <div class="request-description">{{ request.description }}</div>
                <div v-if="request.headers && request.headers.length > 0" class="request-headers">
                  <div class="request-sub-title">Headers :</div>
                  <el-table 
                    :data="request.headers"
                    style="width: 100%">
                    <el-table-column
                      prop="name"
                      label="Header"
                      width="250">
                    </el-table-column>
                    <el-table-column
                      prop="value"
                      label="Value"
                      width="auto">
                    </el-table-column>
                  </el-table>
                </div>
                <div v-if="request.params && request.params.length > 0" class="request-parameters">
                  <div class="request-sub-title">Parameters :</div>
                  <el-table
                      :data="request.params"
                      style="width: 100%">
                      <el-table-column
                        prop="name"
                        label="Parameter"
                        width="250">
                      </el-table-column>
                      <el-table-column
                        prop="value"
                        label="Value"
                        width="auto">
                      </el-table-column>
                  </el-table>
                </div>
                <div class="request-response">
                  <div class="request-sub-title">Response :</div>
                  <pre v-if="request.returnIsJson" v-html="request.return" class="bg-dark">
                  </pre>
                  <div v-else class="request-description">{{ request.return }}</div>
                </div>
              </div>
            </el-collapse-item>
          </el-collapse>
        </el-main>
      </el-container>
    </div>
  </body>
  <script>
    var app = new Vue({
      el: '#app',
      data() {
        return {
        };
      },
      methods: {
        replacer(match, pIndent, pKey, pVal, pEnd) {
            let key = '<span class=json-key>';
            let val = '<span class=json-value>';
            let str = '<span class=json-string>';
            let r = pIndent || '';
            if (pKey)
                r = r + key + pKey.replace(/[": ]/g, '') + '</span>: ';
            if (pVal)
                r = r + (pVal[0] == '"' ? str : val) + pVal + '</span>';
            return r + (pEnd || '');
        },
        toHtml(obj) {
            let jsonLine = /^( *)("[\w]+": )?("[^"]*"|[\w.+-]*)?([,[{])?$/mg;
            return JSON.stringify(obj, null, 3)
                .replace(/&/g, '&amp;').replace(/\\"/g, '&quot;')
                .replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(jsonLine, this.replacer);
        }
      },
      computed: {
        requests(){
          data.forEach(request => {
            if (request.returnIsJson){
              request.return = this.toHtml(request.return);
            }
            switch(request.method) {
              case "POST":
                  request.tag="success";
                  break;
              case "PUT":
                request.tag="warning";
                  break;
              case "DELETE":
                request.tag="danger";
                  break;
              default:
                request.tag="";
            }
          });
          return data;
        }
      }
    })
  </script>
</html>