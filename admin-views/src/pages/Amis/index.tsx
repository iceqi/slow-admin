import 'amis/lib/themes/cxd.css'
import 'amis/lib/helper.css'
import 'amis/sdk/iconfont.css'
import 'amis-ui/lib/locale/en-US'
import './index.less'
import axios from "axios"
import copy from 'copy-to-clipboard'
import '@fortawesome/fontawesome-free/css/all.css'
import {useEffect, useState} from 'react'
import {adminService} from "@/services/admin"
import {useLocation, history} from '@umijs/max'
import {PageContainer} from "@ant-design/pro-components"
import {AlertComponent, render as renderAmis, toast} from 'amis'
import {getSettingItem} from "@/utils/setting";

export default () => {
    const location = useLocation()
    const [schema, setSchema] = useState<any>({})
    const [loading, setLoading] = useState(true)

    const initPage = () => {
        adminService.initPage(location.pathname).then((res) => {
            setSchema(res.data)
            setLoading(false)
        })
    }

    useEffect(() => {
        initPage()
    }, [])


    return (
        <PageContainer loading={loading} header={{title: ''}} className="animate__animated animate__fadeIn">
            <AlertComponent key='alert'/>
            {
                renderAmis(schema, {
                    locale: getSettingItem('locale') == 'en' ? 'en-US' : getSettingItem('locale'),
                }, {
                    fetcher: ({url, method, data}) => adminService.request(url, method, data),
                    isCancel: (value: any) => (axios as any).isCancel(value),
                    copy: content => {
                        copy(content as any)
                        toast.success('内容已复制到粘贴板')
                    },
                    jumpTo: (location: string, action) => {
                        if (action?.blank) {
                            window.open(location)
                        } else {
                            history.push(location.startsWith('/') ? location : '/' + location)
                        }
                    },
                    updateLocation: () => {
                    }
                })
            }
        </PageContainer>
    )
}
